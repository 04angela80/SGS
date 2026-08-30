<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/db.php';

require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$stagiaire_id = isset($_GET['stagiaire_id']) ? intval($_GET['stagiaire_id']) : 0;

if ($stagiaire_id <= 0) {
    die("ID de stagiaire non valide.");
}

try {
    $sql = "SELECT s.nom, s.prenom, s.universite, e.note, e.commentaire, e.date_evaluation 
            FROM stagiaires s
            LEFT JOIN evaluations e ON s.id = e.stagiaire_id
            WHERE s.id = ?";
    
    $stmt = $bdd->prepare($sql);
    $stmt->execute([$stagiaire_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        die("Stagiaire introuvable.");
    }

    if ($data['note'] === null || $data['note'] === '') {
        die("Ce stagiaire n'a pas encore été évalué. L'attestation ne peut pas être générée.");
    }


    $nomComplet = htmlspecialchars(strtoupper($data['nom']) . ' ' . $data['prenom']);
    $universite = !empty(trim($data['universite'] ?? '')) 
                    ? htmlspecialchars($data['universite']) 
                    : "l'Université";
    
    $note = floatval($data['note']);
    $commentaire = htmlspecialchars($data['commentaire']);
    $dateEvaluation = date('d/m/Y', strtotime($data['date_evaluation']));
    $dateAujourdhui = date('d/m/Y');

  
    if ($note >= 16) { 
        $mention = "Très Bien"; 
    } elseif ($note >= 14) { 
        $mention = "Bien"; 
    } elseif ($note >= 12) { 
        $mention = "Assez Bien"; 
    } elseif ($note >= 10) { 
        $mention = "Passable"; 
    } else { 
        $mention = "Insuffisant"; 
    }

    if ($note >= 10) {
        $texteParcours = '
        <p>a effectué et validé son stage pratique au sein de notre établissement. Durant cette période de formation en entreprise, le stagiaire a fait preuve de sérieux, d\'engagement intellectuel et d\'une intégration exemplaire au sein de nos équipes.</p>
        <p>Conformément aux évaluations validées le <span class="highlight">' . $dateEvaluation . '</span>, les performances globales sont consignées ci-dessous :</p>';
        
        $texteConclusion = '<p>En foi de quoi, cette attestation lui est délivrée pour servir et valoir ce que de droit.</p>';
    } else {
        // CAS 2 : ÉCHEC (Non-validation)
        $texteParcours = '
        <p>a effectué un stage pratique au sein de notre établissement. Cependant, il a été constaté que le stagiaire n\'a pas fourni les efforts nécessaires, manquant de rigueur tant dans l\'accomplissement de ses tâches que dans son assiduité globale.</p>
        <p>En conséquence, l\'évaluation finale effectuée en date du <span class="highlight">' . $dateEvaluation . '</span> a conclu à un avis défavorable, consigné ci-dessous :</p>';
        
        $texteConclusion = '<p>En foi de quoi, cette attestation d\'évaluation lui est délivrée à titre de constat officiel de fin de stage non validé.</p>';
    }

} catch (PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}

// 6. Construction du template HTML optimisé pour tenir sur UNE SEULE PAGE
$html = '
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Attestation de fin de stage</title>
    <style>
        @page {
            margin: 12mm 15mm;
        }
        body {
            font-family: "Helvetica", "Arial", sans-serif;
            color: #2d3748;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .header {
            width: 100%;
            border-bottom: 2px solid #0056b3;
            padding-bottom: 10px;
            margin-bottom: 25px;
        }
        .header-logo {
            float: left;
            font-size: 22px;
            font-weight: bold;
            color: #0056b3;
            text-transform: uppercase;
        }
        .header-date {
            float: right;
            font-size: 13px;
            color: #718096;
            text-align: right;
        }
        .clearfix {
            clear: both;
        }
        .title {
            text-align: center;
            margin: 20px 0;
        }
        .title h1 {
            color: #003d80;
            font-size: 24px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 3px;
        }
        .title p {
            font-size: 13px;
            color: #4a5568;
            font-style: italic;
            margin: 0;
        }
        .content {
            font-size: 15px;
            text-align: justify;
            margin-bottom: 20px;
        }
        .highlight {
            font-weight: bold;
            color: #0056b3;
        }
        .results-box {
            background-color: #f7fafc;
            border: 1px solid #e2e8f0;
            border-left: 5px solid #0056b3;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        .results-box table {
            width: 100%;
            border-collapse: collapse;
        }
        .results-box td {
            padding: 6px 0;
            font-size: 14px;
        }
        .results-box td.label {
            font-weight: bold;
            width: 35%;
            color: #4a5568;
        }
        
        .signature-area {
            margin-top: 25px;
            width: 100%;
            page-break-inside: avoid;
        }
        
        .stamp-container {
            float: left;
            width: 130px;
            height: 130px;
            border: 4px double #0056b3;
            border-radius: 50%;
            position: relative;
            text-align: center;
            background: rgba(0, 86, 179, 0.02);
            transform: rotate(-8deg);
            margin-left: 30px;
            margin-top: -5px;
        }
        .stamp-inner {
            width: 110px;
            height: 110px;
            border: 1px dashed #0056b3;
            border-radius: 50%;
            margin: 6px auto;
            position: relative;
        }
        .stamp-text-top {
            font-size: 7px;
            font-weight: bold;
            color: #0056b3;
            position: absolute;
            top: 10px;
            left: 0;
            right: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stamp-text-bottom {
            font-size: 7px;
            font-weight: bold;
            color: #0056b3;
            position: absolute;
            bottom: 10px;
            left: 0;
            right: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stamp-center {
            position: absolute;
            top: 35px;
            left: 0;
            right: 0;
            text-align: center;
        }
        .stamp-star {
            color: #0056b3;
            font-size: 11px;
            margin-bottom: 1px;
        }
        .stamp-status {
            font-size: 9px;
            font-weight: 900;
            color: #0056b3;
            text-transform: uppercase;
            border-top: 1px solid #0056b3;
            border-bottom: 1px solid #0056b3;
            padding: 1px 0;
            display: inline-block;
            letter-spacing: 1px;
        }

        .signature-title {
            float: right;
            width: 250px;
            text-align: center;
            font-weight: bold;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 50px;
        }
        .footer {
            position: absolute;
            bottom: -5px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            color: #a0aec0;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="header-logo">SGS Admin</div>
        <div class="header-date">
            Fait le ' . $dateAujourdhui . '<br>
            Réf : AT-' . date('Y') . '-' . str_pad($stagiaire_id, 4, '0', STR_PAD_LEFT) . '
        </div>
        <div class="clearfix"></div>
    </div>

    <div class="title">
        <h1>Attestation d\'Évaluation de Fin de Stage</h1>
        <p>Document officiel délivré par le Système de Gestion des Stagiaires</p>
    </div>

    <div class="content">
        <p>Nous soussignés, la Direction administrative du centre de stage, certifions par la présente que :</p>
        
        <p style="text-align: center; font-size: 16px; margin: 12px 0;">
            M./Mme <span class="highlight">' . $nomComplet . '</span>,<br>
            <span style="font-size: 14px; color: #4a5568;">étudiant(e) provenance de : <strong>' . $universite . '</strong></span>
        </p>

        ' . $texteParcours . '

        <div class="results-box">
            <table>
                <tr>
                    <td class="label">Note de stage global :</td>
                    <td><span class="highlight" style="font-size: 16px;">' . number_format($note, 2, '.', '') . ' / 20</span></td>
                </tr>
                <tr>
                    <td class="label">Mention obtenue :</td>
                    <td><strong>' . $mention . '</strong></td>
                </tr>
                <tr>
                    <td class="label">Appréciation de l\'encadrant :</td>
                    <td style="font-style: italic; color: #4a5568;">« ' . $commentaire . ' »</td>
                </tr>
            </table>
        </div>

        ' . $texteConclusion . '
    </div>

    <div class="signature-area">
        <!-- LE CACHET SGS DESIGN (À GAUCHE) -->
        <div class="stamp-container">
            <div class="stamp-inner">
                <div class="stamp-text-top">SGS • DIRECTOIRE</div>
                <div class="stamp-center">
                    <div class="stamp-star">★</div>
                    <div class="stamp-status">CERTIFIÉ</div>
                </div>
                <div class="stamp-text-bottom">ADMINISTRATION GENERALE</div>
            </div>
        </div>

        <!-- ZONE DE SIGNATURE (À DROITE) -->
        <div class="signature-title">
            La Direction Générale<br>
            <span style="font-size: 11px; font-weight: normal; color: #718096;">Signature & cachet autorisés</span>
        </div>
        <div class="clearfix"></div>
    </div>

    <div class="footer">
        Systeme de Gestion des Stagiaires • Généré numériquement le ' . $dateAujourdhui . ' • Contact : support@sgs-admin.com
    </div>

</body>
</html>
';

// 7. Initialisation de Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); 

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);

// Format de page A4 vertical
$dompdf->setPaper('A4', 'portrait');

// Rendu du HTML en PDF
$dompdf->render();

// Envoi du fichier généré directement au navigateur pour téléchargement
$dompdf->stream("Attestation_Stage_" . str_replace(' ', '_', $data['nom']) . ".pdf", array("Attachment" => true));
exit();