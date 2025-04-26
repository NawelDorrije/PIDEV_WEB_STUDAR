<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

class LogementOptionsId
{
    private int $id_logement;
    private int $id_option;

    public function __construct(int $id_logement, int $id_option)
    {
        $this->id_logement = $id_logement;
        $this->id_option = $id_option;
    }

    public function getIdLogement(): int
    {
        return $this->id_logement;
    }

    public function getIdOption(): int
    {
        return $this->id_option;
    }
}
