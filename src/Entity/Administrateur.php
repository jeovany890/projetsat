<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Administrateur extends Utilisateur
{
    // Pas d'attributs spécifiques pour Admin
    // Toutes les méthodes sont héritées de Utilisateur
}