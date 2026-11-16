(function() {
    function initChat() {
        if (document.getElementById('chat-widget-button')) return;

        const chatHTML = `
            <div id="chat-widget-button" style="position: fixed; bottom: 20px; right: 20px; width: 60px; height: 60px; background-color: #007bff; color: white; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 28px; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 9999;">💬</div>

            <div id="chat-widget-box" style="display: none; position: fixed; bottom: 90px; right: 20px; width: 340px; height: 430px; background: white; border-radius: 12px; box-shadow: 0 5px 25px rgba(0,0,0,0.25); border: 1px solid #ddd; flex-direction: column; overflow: hidden; z-index: 9999; font-family: Arial, sans-serif;">
                <div style="background: #007bff; color: white; padding: 12px 15px; display: flex; justify-content: space-between; align-items: center; font-weight: bold;">
                    <span>🤖 Ventas & Soporte IA</span>
                    <span id="chat-widget-close" style="cursor: pointer; font-size: 18px;">✖</span>
                </div>
                <div id="chat-messages" style="flex: 1; padding: 12px; overflow-y: auto; background: #f9f9f9; font-size: 14px;">
                    <div style="text-align: left; margin-bottom: 10px;">
                        <span style="background: #e9ecef; color: #333; padding: 8px 12px; border-radius: 10px; display: inline-block; max-width: 85%;">¡Hola! Pregúntame sobre nuestros productos o precios.</span>
                    </div>
                </div>
                <div style="display: flex; padding: 10px; background: white; border-top: 1px solid #eee;">
                    <input type="text" id="chat-input" placeholder="Escribe tu mensaje..." style="flex: 1; padding: 8px 12px; border: 1px solid #ccc; border-radius: 20px; outline: none; font-size: 14px;">
                    <button id="chat-send-btn" style="margin-left: 8px; background: #007bff; color: white; border: none; padding: 8px 14px; border-radius: 20px; cursor: pointer; font-weight: bold;">➢</button>
                </div>
            </div>
        `;

        const div = document.createElement('div');
        div.innerHTML = chatHTML;
        document.body.appendChild(div);

        document.getElementById('chat-widget-button').addEventListener('click', window.toggleChatWindow);
        document.getElementById('chat-widget-close').addEventListener('click', window.toggleChatWindow);
        document.getElementById('chat-send-btn').addEventListener('click', window.enviarMensajeWeb);
        document.getElementById('chat-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') window.enviarMensajeWeb();
        });
    }

    window.toggleChatWindow = function() {
        const box = document.getElementById('chat-widget-box');
        if (box) {
            box.style.display = (box.style.display === 'none' || box.style.display === '') ? 'flex' : 'none';
        }
    };

    window.enviarMensajeWeb = async function() {
        const input = document.getElementById('chat-input');
        const text = input.value.trim();
        if (!text) return;

        const box = document.getElementById('chat-messages');
        box.innerHTML += `<div style="text-align: right; margin-bottom: 10px;"><span style="background: #007bff; color: white; padding: 8px 12px; border-radius: 10px; display: inline-block; max-width: 85%; text-align: left;">${text}</span></div>`;
        input.value = '';
        box.scrollTop = box.scrollHeight;

        const loadingId = 'loading-' + Date.now();
        box.innerHTML += `<div id="${loadingId}" style="text-align: left; margin-bottom: 10px;"><span style="background: #e9ecef; color: #666; padding: 8px 12px; border-radius: 10px; display: inline-block; font-style: italic;">Pensando...</span></div>`;
        box.scrollTop = box.scrollHeight;

        const host = window.location.hostname || 'localhost';
        const targetUrl = `http://${host}:5000/api/chat`;

        try {
            const res = await fetch(targetUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pregunta: text, mensaje: text })
            });
            const data = await res.json();
            const loadingEl = document.getElementById(loadingId);
            if (loadingEl) loadingEl.remove();

            const respuestaTexto = data.respuesta || data.mensaje || 'Sin respuesta del bot.';
            box.innerHTML += `<div style="text-align: left; margin-bottom: 10px;"><span style="background: #e9ecef; color: #333; padding: 8px 12px; border-radius: 10px; display: inline-block; max-width: 85%;">${respuestaTexto}</span></div>`;
        } catch (e) {
            const loadingEl = document.getElementById(loadingId);
            if (loadingEl) loadingEl.remove();
            box.innerHTML += `<div style="text-align: left; margin-bottom: 10px;"><span style="background: #f8d7da; color: #721c24; padding: 8px 12px; border-radius: 10px; display: inline-block; max-width: 85%;">Error al conectar con la API del chatbot.</span></div>`;
        }
        box.scrollTop = box.scrollHeight;
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initChat);
    } else {
        initChat();
    }
})();
