from flask import Flask, request, jsonify
from flask_cors import CORS
import chromadb
import pymysql
import requests
import re
import os

app = Flask(__name__)
CORS(app)

MYSQL_HOST = os.getenv("MYSQL_HOST", "192.168.100.13")
chroma_client = chromadb.HttpClient(host='localhost', port=8000)

def sincronizar_catalogo():
    conn = pymysql.connect(
        host=MYSQL_HOST,
        user='app_user',
        password='PasswordSeguro123!',
        database='ecommerce',
        charset='utf8mb4'
    )
    docs, ids, metas = [], [], []
    with conn.cursor() as cur:
        cur.execute("SELECT id, modelo, tALLA, precio, stock FROM catalogo_zapateria")
        for row in cur.fetchall():
            docs.append(f"Tenis/Calzado: {row[1]} | Talla: {row[2]} | Precio: ${row[3]} | Disponibles: {row[4]} pares")
            ids.append(f"zap_{row[0]}")
            metas.append({"categoria": "zapateria"})

        cur.execute("SELECT id, articulo, precio, stock FROM catalogo_muebles")
        for row in cur.fetchall():
            docs.append(f"Mueble: {row[1]} | Precio: ${row[2]} | Disponibles: {row[3]} unidades")
            ids.append(f"mue_{row[0]}")
            metas.append({"categoria": "muebles"})
    conn.close()

    try:
        chroma_client.delete_collection(name="tienda")
    except Exception:
        pass
    col = chroma_client.get_or_create_collection(name="tienda")
    if docs:
        col.add(documents=docs, ids=ids, metadatas=metas)
    return col, len(docs)

coleccion, total_productos = sincronizar_catalogo()
print(f"Catálogo sincronizado con {total_productos} productos reales desde MySQL.")

def es_seguro(texto):
    prohibidas = [r"ignora", r"instrucciones", r"system prompt", r"actúa como"]
    for p in prohibidas:
        if re.search(p, texto, re.IGNORECASE):
            return False
    return True

@app.route('/api/sync', methods=['POST', 'GET'])
def sync_route():
    global coleccion
    coleccion, total = sincronizar_catalogo()
    return jsonify({"status": "ok", "productos_cargados": total})

@app.route('/api/chat', methods=['POST'])
def chat():
    data = request.json or {}
    pregunta = data.get("pregunta", "").strip()

    if not pregunta:
        return jsonify({"respuesta": "Por favor escribe algo."}), 400

    if not es_seguro(pregunta):
        return jsonify({
            "respuesta": "🛡️ SISTEMA: Alerta de seguridad. Intento de inyección de prompt bloqueado."
        })

    try:
        q_lower = pregunta.lower()
        filtro = None
        if any(w in q_lower for w in ["zapato", "zapateria", "zapatería", "tenis", "calzado", "talla"]):
            filtro = {"categoria": "zapateria"}
        elif any(w in q_lower for w in ["mueble", "silla", "oficina"]):
            filtro = {"categoria": "muebles"}

        total = max(1, coleccion.count())
        if filtro:
            resultados = coleccion.query(query_texts=[pregunta], n_results=min(3, total), where=filtro)
        else:
            resultados = coleccion.query(query_texts=[pregunta], n_results=min(4, total))

        docs_encontrados = resultados['documents'][0] if resultados['documents'] else []
        contexto = "\n".join(f"- {d}" for d in docs_encontrados) if docs_encontrados else "No hay productos en esa categoría."

        respuesta = requests.post(
            "http://192.168.100.20:11434/api/chat",
            json={
                "model": "qwen2:0.5b",
                "stream": False,
                "keep_alive": "30m",
                "messages": [
                    {
                        "role": "system",
                        "content": "Eres el vendedor de la tienda. Responde en español en 1 o 2 oraciones usando ÚNICAMENTE los datos exactos de la lista de inventario. No inventes artículos ni cambies precios."
                    },
                    {
                        "role": "user",
                        "content": f"Inventario:\n{contexto}\n\nPregunta del cliente: {pregunta}"
                    }
                ],
                "options": {
                    "num_thread": 2,
                    "num_predict": 70,
                    "temperature": 0.0
                }
            }
        ).json()

        texto_final = respuesta.get("message", {}).get("content", "Sin respuesta").strip()
        return jsonify({"respuesta": texto_final})
    except Exception as e:
        return jsonify({"respuesta": f"Error interno: {str(e)}"}), 500

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
