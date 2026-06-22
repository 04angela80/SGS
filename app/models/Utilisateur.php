<?php
require_once __DIR__ . '/DB.php';

abstract class Utilisateur
{
    protected $id;
    protected $nom;
    protected $email;
    protected $password;
    protected $role;

    public function __construct($data = [])
    {
        foreach ($data as $k => $v) {
            if (property_exists($this, $k)) {
                $this->$k = $v;
            }
        }
    }

    public function getId() { return $this->id; }
    public function getNom() { return $this->nom; }
    public function getEmail() { return $this->email; }
    public function getRole() { return $this->role; }

    public function setPassword(string $plain)
    {
        $this->password = password_hash($plain, PASSWORD_DEFAULT);
    }

    public function verifyPassword(string $plain): bool
    {
        return password_verify($plain, $this->password);
    }

    public static function findByEmail(string $email)
    {
        $pdo = DB::get();
        $stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if (!$row) return null;

        // map role to concrete class
        $role = $row['role'] ?? '';
        switch (strtolower($role)) {
            case 'etudiant': $cls = 'Etudiant'; break;
            case 'enseignant': $cls = 'Enseignant'; break;
            case 'assistant': $cls = 'Assistant'; break;
            case 'doyen': $cls = 'Doyen'; break;
            case 'vicedoyen': $cls = 'ViceDoyen'; break;
            case 'apparitaire': $cls = 'Apparitaire'; break;
            default: $cls = 'class_alias';
        }

        if (class_exists($cls)) {
            return new $cls($row);
        }

        $u = new class($row) extends Utilisateur {};
        return $u;
    }
}
