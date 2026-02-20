<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviando por WhatsApp...</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .loading-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .spinner {
            width: 60px;
            height: 60px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #25D366;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .success-icon {
            font-size: 60px;
            color: #25D366;
            margin-bottom: 20px;
        }
        .error-icon {
            font-size: 60px;
            color: #dc3545;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="loading-container" id="container">
        <div class="spinner" id="spinner"></div>
        <h4 id="status">Preparando mensaje de WhatsApp...</h4>
        <p class="text-muted" id="details">Por favor espere...</p>
    </div>

    <script>
        // Obtener ID de la URL
        const urlParams = new URLSearchParams(window.location.search);
        const invitationId = urlParams.get('id');

        if (!invitationId) {
            showError('No se especificó la invitación');
        } else {
            enviarWhatsApp(invitationId);
        }

        async function enviarWhatsApp(id) {
            try {
                // Llamar al servicio JSON
                const response = await fetch(`enviar_whatsapp_directo.php?id=${id}`);
                const data = await response.json();

                if (data.error) {
                    showError(data.error, data);
                    return;
                }

                if (data.success && data.whatsapp_url) {
                    // Mostrar éxito
                    showSuccess(data);
                    
                    // Abrir WhatsApp después de 1 segundo
                    setTimeout(() => {
                        window.location.href = data.whatsapp_url;
                        
                        // Mostrar mensaje de confirmación después de 2 segundos
                        setTimeout(() => {
                            document.getElementById('status').textContent = '? WhatsApp Abierto';
                            document.getElementById('details').innerHTML = `
                                <p>El mensaje fue enviado a WhatsApp Web</p>
                                <a href="index.php" class="btn btn-primary mt-3">
                                    <i class="fas fa-arrow-left"></i> Volver a Invitaciones
                                </a>
                            `;
                        }, 2000);
                    }, 1000);
                } else {
                    showError('Respuesta inválida del servidor');
                }
            } catch (error) {
                showError('Error de conexión: ' + error.message);
            }
        }

        function showSuccess(data) {
            document.getElementById('spinner').style.display = 'none';
            document.getElementById('status').innerHTML = '<div class="success-icon"><i class="fab fa-whatsapp"></i></div>¡Mensaje Generado!';
            document.getElementById('details').innerHTML = `
                <p><strong>Club:</strong> ${escapeHtml(data.club)}</p>
                <p><strong>Teléfono:</strong> ${escapeHtml(data.telefono)}</p>
                <p class="text-success"><i class="fas fa-check-circle"></i> Abriendo WhatsApp...</p>
            `;
        }

        function showError(message, data = null) {
            document.getElementById('spinner').style.display = 'none';
            document.getElementById('status').innerHTML = '<div class="error-icon"><i class="fas fa-exclamation-circle"></i></div>Error';
            
            let detailsHtml = `<p class="text-danger">${escapeHtml(message)}</p>`;
            
            if (data && data.club_nombre) {
                detailsHtml += `<p><strong>Club:</strong> ${escapeHtml(data.club_nombre)}</p>`;
            }
            
            detailsHtml += `
                <a href="index.php" class="btn btn-primary mt-3">
                    <i class="fas fa-arrow-left"></i> Volver a Invitaciones
                </a>
            `;
            
            document.getElementById('details').innerHTML = detailsHtml;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>

