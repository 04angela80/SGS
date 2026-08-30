<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// On force l'exercice sur l'année 2026
$annee_courante = 2026;
$semaine_req = isset($_GET['semaine']) ? trim($_GET['semaine']) : "2026-W" . date('W');

preg_match('/^2026-W(\d{2})$/', $semaine_req, $matches);
$num_semaine = isset($matches[1]) ? intval($matches[1]) : intval(date('W'));

// Calcul des jours de la semaine
$date_calcul = new DateTime();
$date_calcul->setISODate(2026, $num_semaine);
$lundi = clone $date_calcul;
$lundi->modify('monday this week');

$jours = [];
for ($i = 0; $i < 5; $i++) {
    $d = clone $lundi;
    if ($i > 0) { $d->modify("+" . $i . " days"); }
    $jours[] = $d->format('Y-m-d');
}

// Configuration des headers pour forcer le téléchargement immédiat en Excel / CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="Rapport_Semaine_' . $num_semaine . '_Annee_2026.csv"');

// Ouverture du flux de sortie (génère le fichier à la volée)
$output = fopen('php://output', 'w');

// UTF-8 pour Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Ligne d'entête du tableau
fputcsv($output, ['Stagiaire', 'Filiere', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Assiduite']);

try {
    // Récupération des stagiaires actifs
    $stagiaires = $bdd->query("SELECT id, nom, prenom, filiere FROM stagiaires WHERE LOWER(statut) IN ('validé','valide') ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Récupération des présences
    $stmt = $bdd->prepare("SELECT stagiaire_id, date_fiche, etat_presence FROM suivi WHERE date_fiche BETWEEN :d AND :f");
    $stmt->execute(array(':d' => $jours[0], ':f' => $jours[4]));
    $pointages = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $pointages[$p['stagiaire_id']][$p['date_fiche']] = $p['etat_presence'];
    }

    // Écriture des données de chaque stagiaire
    foreach ($stagiaires as $stg) {
        $sid = $stg['id'];
        $p_lun = $pointages[$sid][$jours[0]] ?? '-';
        $p_mar = $pointages[$sid][$jours[1]] ?? '-';
        $p_mer = $pointages[$sid][$jours[2]] ?? '-';
        $p_jeu = $pointages[$sid][$jours[3]] ?? '-';
        $p_ven = $pointages[$sid][$jours[4]] ?? '-';

        $total = 0;
        if ($p_lun === 'présent') $total++;
        if ($p_mar === 'présent') $total++;
        if ($p_mer === 'présent') $total++;
        if ($p_jeu === 'présent') $total++;
        if ($p_ven === 'présent') $total++;

        $assiduite = "Irrégulier ($total/5 j)";
        if ($total >= 4) $assiduite = "Régulier ($total/5 j)";
        if ($total == 0) $assiduite = "Négligent";

        fputcsv($output, [
            $stg['nom'] . ' ' . $stg['prenom'],
            $stg['filiere'],
            ($p_lun === 'présent' ? 'P' : ($p_lun === 'absent' ? 'A' : '-')),
            ($p_mar === 'présent' ? 'P' : ($p_mar === 'absent' ? 'A' : '-')),
            ($p_mer === 'présent' ? 'P' : ($p_mer === 'absent' ? 'A' : '-')),
            ($p_jeu === 'présent' ? 'P' : ($p_jeu === 'absent' ? 'A' : '-')),
            ($p_ven === 'présent' ? 'P' : ($p_ven === 'absent' ? 'A' : '-')),
            $assiduite
        ]);
    }
} catch (Exception $e) {
    // En cas d'erreur
}

fclose($output);
exit();