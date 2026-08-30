
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>SGS - VALIDATION</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Poppins', sans-serif;
}

/* Fond élégant */
body {
    min-height: 100vh;
    background: radial-gradient(circle at top, #0f2027, #203a43, #2c5364);
    display: flex;
    justify-content: center;
    align-items: center;
    overflow: hidden;
    color: #fff;
}

/* Carte centrale */
.confirmation-container {
    position: relative;
    z-index: 10;
}

.card-confirmation {
    background: rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(12px);
    padding: 50px 60px;
    border-radius: 20px;
    text-align: center;
    max-width: 500px;
    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.4);
    animation: pop 0.8s ease;
}

@keyframes pop {
    from {
        transform: scale(0.8);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

/* LOGO */
.logo-container {
    margin-bottom: 25px;
}

.logo {
    width: 110px;
    height: 110px;
    object-fit: cover;
    border-radius: 50%;
    background: #fff;
    padding: 8px;
    box-shadow: 0 15px 30px rgba(0,0,0,0.3);
}

/* Texte */
.card-confirmation h1 {
    font-size: 32px;
    margin-bottom: 15px;
}

.card-confirmation p {
    font-size: 16px;
    opacity: 0.9;
    margin-bottom: 35px;
    line-height: 1.6;
}

/* Bouton */
.btn {
    padding: 14px 32px;
    border: none;
    border-radius: 30px;
    background: linear-gradient(135deg, #00c6ff, #0072ff);
    color: #fff;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    transition: transform 0.3s, box-shadow 0.3s;
}

.btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 30px rgba(0, 114, 255, 0.4);
}

/* =======================
   FEU D’ARTIFICE (CSS)
======================= */
.fireworks span {
    position: absolute;
    width: 6px;
    height: 6px;
    background: white;
    border-radius: 50%;
    animation: explode 2.5s infinite ease-out;
    opacity: 0;
}

.fireworks span:nth-child(1) { top: 20%; left: 30%; animation-delay: 0s; }
.fireworks span:nth-child(2) { top: 40%; left: 70%; animation-delay: 0.5s; }
.fireworks span:nth-child(3) { top: 60%; left: 50%; animation-delay: 1s; }
.fireworks span:nth-child(4) { top: 30%; left: 80%; animation-delay: 1.5s; }
.fireworks span:nth-child(5) { top: 50%; left: 20%; animation-delay: 2s; }

@keyframes explode {
    0% {
        transform: scale(0);
        opacity: 1;
        box-shadow:
            0 0 #ff0,
            0 0 #0ff,
            0 0 #f0f;
    }
    100% {
        transform: scale(4);
        opacity: 0;
        box-shadow:
            50px 0 #ff0,
            -50px 0 #0ff,
            0 50px #f0f,
            0 -50px #0f0;
    }
}
    </style>
</head>
<body>

<!-- Effet feu d’artifice -->
<div class="fireworks">
    <span></span><span></span><span></span><span></span><span></span>
</div>

<div class="confirmation-container">
    <div class="card-confirmation">
        <img src="../../LOGO.jpeg" alt="Logo SGS" class="logo">
        <h1>Inscription réussie</h1>
        <p>
            Votre inscription a été validé avec succès.<br>
        </p>
        

        <button class="btn" onclick="window.location.href='code.html'">
    Accéder à votre code personnel
</button>
    </div>
</div>

</body>
</html>