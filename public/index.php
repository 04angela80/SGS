

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>SGS - Accueil</title>
    <style>
        /* RESET */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Poppins', sans-serif;
            height: 100vh;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            overflow: hidden;
            color: #fff;
        }

        .hero {
            position: relative;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(5px);
        }

        .hero-content {
            position: relative;
            z-index: 2;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            padding: 45px 40px;
            border-radius: 28px;
            max-width: 520px;
            width: 90%;
            text-align: center;
            box-shadow: 0 30px 60px rgba(0,0,0,0.35);
            animation: fadeUp 1s ease forwards;
        }

        .logo-container { margin-bottom: 25px; }
        .logo {
            width: 110px; height: 110px; object-fit: cover;
            border-radius: 50%; background: #fff; padding: 8px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.3);
        }

        .hero-content h1 { font-size: 30px; font-weight: 700; margin-bottom: 12px; }
        .hero-content p { font-size: 15px; color: #dbeafe; margin-bottom: 35px; }

        .hero-buttons {
            display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;
        }

        .btn {
            text-decoration: none; padding: 14px 28px; border-radius: 50px;
            font-weight: 600; font-size: 15px; color: #fff;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            box-shadow: 0 15px 35px rgba(37,99,235,0.45);
            transition: all 0.3s ease;
        }
        .btn:hover { transform: translateY(-3px) scale(1.05); box-shadow: 0 25px 50px rgba(37,99,235,0.6); }

        .btn-alt {
            background: linear-gradient(135deg, #06b6d4, #0e7490);
            box-shadow: 0 15px 35px rgba(6,182,212,0.45);
        }
        .btn-alt:hover { box-shadow: 0 25px 50px rgba(6,182,212,0.6); }

        @keyframes fadeUp {
            0% { opacity: 0; transform: translateY(40px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 600px) {
            .hero-content { padding: 35px 25px; }
            .hero-content h1 { font-size: 24px; }
            .logo { width: 90px; height: 90px; }
        }
    </style>
</head>
<body>

<section class="hero">
    <div class="overlay"></div>
    <div class="hero-content">
        <div class="logo-container">
            <img src="../LOGO.jpeg" alt="Logo SGS" class="logo">
        </div>
        <h1>Système de Gestion des Stagiaires</h1>
        <p>Gérez, suivez et évaluez vos stagiaires avec élégance et efficacité</p>

        <div class="hero-buttons">
            <!-- Connexion Administrateur -->
            <a href="admin/CONNEXION.html" class="btn">Connexion Admin</a>

          <a href="stagiaire/connexion_stagiaire.php" class="btn btn-alt">Connexion Stagiaire</a>
</div>
    </div>
</section>

</body>
</html>z    
