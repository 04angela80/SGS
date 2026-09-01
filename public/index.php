<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <!-- Balise viewport essentielle pour le rendu responsive sur mobile -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGS - Accueil</title>
    
    <!-- Polices Google -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        /* RESET & BASE */
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }

        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            /* Permet le défilement sur les écrans très courts au lieu de couper le contenu */
            overflow-x: hidden;
            overflow-y: auto;
        }

        .hero {
            position: relative;
            width: 100%;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 15px;
        }

        .overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(5px);
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            padding: 45px 30px;
            border-radius: 28px;
            max-width: 520px;
            width: 100%;
            text-align: center;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.35);
            border: 1px solid rgba(255, 255, 255, 0.15);
            animation: fadeUp 0.8s ease forwards;
        }

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
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
            transition: transform 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05);
        }

        .hero-content h1 { 
            font-size: 1.875rem; /* 30px équivalent fluide */
            font-weight: 700; 
            margin-bottom: 12px; 
            line-height: 1.2;
        }

        .hero-content p { 
            font-size: 0.95rem; 
            color: #dbeafe; 
            margin-bottom: 35px; 
            line-height: 1.5;
        }

        .hero-buttons {
            display: flex; 
            gap: 15px; 
            justify-content: center; 
            flex-wrap: wrap;
            width: 100%;
        }

        .btn {
            text-decoration: none; 
            padding: 14px 28px; 
            border-radius: 50px;
            font-weight: 600; 
            font-size: 0.95rem; 
            color: #fff;
            background: linear-gradient(135deg, #2563eb, #1e40af);
            box-shadow: 0 15px 35px rgba(37, 99, 235, 0.45);
            transition: all 0.3s ease;
            display: inline-block;
            text-align: center;
            flex: 1;
            min-width: 200px; /* S'assure d'une bonne largeur sur écran moyen */
        }

        .btn:hover { 
            transform: translateY(-3px) scale(1.03); 
            box-shadow: 0 25px 50px rgba(37, 99, 235, 0.6); 
        }

        .btn-alt {
            background: linear-gradient(135deg, #06b6d4, #0e7490);
            box-shadow: 0 15px 35px rgba(6, 182, 212, 0.45);
        }

        .btn-alt:hover { 
            box-shadow: 0 25px 50px rgba(6, 182, 212, 0.6); 
        }

        /* ANIMATION */
        @keyframes fadeUp {
            0% { opacity: 0; transform: translateY(40px); }
            100% { opacity: 1; transform: translateY(0); }
        }

        /* MEDIA QUERIES (RESPONSIVE TABLETTE ET MOBILE) */

        /* Tablettes & Petits Écrans (<= 768px) */
        @media (max-width: 768px) {
            .hero-content {
                padding: 40px 25px;
                max-width: 480px;
            }

            .hero-content h1 {
                font-size: 1.65rem;
            }
        }

        /* Smartphones (<= 480px) */
        @media (max-width: 480px) {
            .hero-content {
                padding: 30px 20px;
                border-radius: 20px;
            }

            .logo {
                width: 85px;
                height: 85px;
                padding: 6px;
            }

            .hero-content h1 {
                font-size: 1.4rem;
            }

            .hero-content p {
                font-size: 0.875rem;
                margin-bottom: 25px;
            }

            .hero-buttons {
                flex-direction: column; /* Empile les boutons verticalement sur mobile */
                gap: 12px;
            }

            .btn {
                width: 100%;
                padding: 12px 20px;
                font-size: 0.9rem;
            }
        }

        /* Adaptation pour les écrans en mode paysage très courts */
        @media (max-height: 600px) {
            .hero {
                padding: 30px 15px;
            }
            .logo-container {
                margin-bottom: 15px;
            }
            .logo {
                width: 70px;
                height: 70px;
            }
            .hero-content p {
                margin-bottom: 20px;
            }
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

            <!-- Connexion Stagiaire -->
            <a href="stagiaire/connexion_stagiaire.php" class="btn btn-alt">Connexion Stagiaire</a>
        </div>
    </div>
</section>

</body>
</html>