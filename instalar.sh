#!/bin/bash
set -e
RUTA=$(pwd)
USUARIO=$(whoami)

echo ">>> 1. Levantando contenedores Docker..."
sudo docker compose up -d
sleep 5
sudo docker compose exec -T ia_ollama ollama pull qwen2:0.5b || true

echo ">>> 2. Creando entorno Python e instalando dependencias..."
python3 -m venv "$RUTA/ia_env"
"$RUTA/ia_env/bin/pip" install -q -r "$RUTA/requirements.txt"

echo ">>> 3. Abriendo puerto 5000 y configurando servicio automático..."
sudo ufw allow 5000/tcp 2>/dev/null || true
MYSQL_IP=$(sudo docker inspect -f '{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' mysql_primary)

sudo tee /etc/systemd/system/chatbot-ia.service > /dev/null << SERVICE
[Unit]
Description=API Chatbot RAG Ventas y Soporte IA
After=network.target docker.service
Wants=docker.service

[Service]
User=$USUARIO
WorkingDirectory=$RUTA
Environment="MYSQL_HOST=$MYSQL_IP"
ExecStart=$RUTA/ia_env/bin/python -u $RUTA/app.py
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
SERVICE

sudo systemctl daemon-reload
sudo systemctl enable --now chatbot-ia.service
echo ">>> ¡Listo! Todo levantado y prendido en automático."
