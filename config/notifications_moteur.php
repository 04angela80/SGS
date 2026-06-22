<?php
/**
 * MOTEUR DE NOTIFICATIONS - VERSION SÉCURISÉE AVEC BACKTICKS SÉCURITÉ MOTS RÉSERVÉS SQL
 */

/**
 * 1. FONCTION POUR AJOUTER UNE NOTIFICATION
 */
function ajouterNotification($bdd, $user_id, $type, $titre, $contenu, $statut = 'non_lu') {
    try {
        $contenu_complet = "[" . $titre . "] " . $contenu;

        // 🌟 CORRECTION CRITIQUE : Utilisation de `type` avec des backticks car 'type' est un mot réservé SQL !
        $req = $bdd->prepare("
            INSERT INTO notifications (user_id, `type`, contenu, date_notification, statut) 
            VALUES (:user_id, :type, :contenu, NOW(), :statut)
        ");
        
        $resultat = $req->execute([
            ':user_id' => $user_id,
            ':type'    => $type,
            ':contenu' => $contenu_complet,
            ':statut'  => $statut
        ]);

        return $resultat;
    } catch (PDOException $e) {
        die("Erreur Moteur Notifications (Ajout) : " . $e->getMessage());
    }
}

/**
 * 2. FONCTION POUR COMPTER LES NOTIFICATIONS NON LUES
 */
function compterNotificationsNonLues($bdd, $user_id, $type_compte) {
    try {
        // 🌟 CORRECTION : Sécurisation avec backticks et paramètres nommés
        $req = $bdd->prepare("
            SELECT COUNT(*) AS total 
            FROM notifications 
            WHERE user_id = :user_id AND `type` = :type_compte AND statut = 'non_lu'
        ");
        
        $req->execute([
            ':user_id'     => $user_id,
            ':type_compte' => $type_compte
        ]);
        
        $resultat = $req->fetch(PDO::FETCH_ASSOC);
        return $resultat ? (int)$resultat['total'] : 0;
    } catch (PDOException $e) {
        die("Erreur Moteur Notifications (Compteur) : " . $e->getMessage());
    }
    
}

/**
 * 3. FONCTION POUR RÉCUPÉRER LES NOTIFICATIONS RÉCENTES
 */
function recupererNotificationsRecentes($bdd, $user_id, $type_compte, $limite = 5) {
    try {
        // 🌟 CORRECTION : Sécurisation avec backticks et paramètres nommés
        $req = $bdd->prepare("
            SELECT id_notification, `type`, contenu, date_notification, statut 
            FROM notifications 
            WHERE user_id = :user_id AND `type` = :type_compte 
            ORDER BY date_notification DESC 
            LIMIT :limite
        ");
        
        $req->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $req->bindValue(':type_compte', $type_compte, PDO::PARAM_STR);
        $req->bindValue(':limite', $limite, PDO::PARAM_INT);
        $req->execute();
        
        return $req->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Erreur Moteur Notifications (Affichage) : " . $e->getMessage());
    }
}