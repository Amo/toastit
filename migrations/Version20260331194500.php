<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260331194500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize recurrence values to ISO 8601 intervals';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE meeting SET recurrence = 'P1D' WHERE recurrence IN ('quotidien', '1 × jour(s)')");
        $this->addSql("UPDATE meeting SET recurrence = 'P1W' WHERE recurrence IN ('hebdo', '1 × semaine')");
        $this->addSql("UPDATE meeting SET recurrence = 'P2W' WHERE recurrence IN ('bi-hebdo', '1 × deux semaines', '2 × semaine')");
        $this->addSql("UPDATE meeting SET recurrence = 'P1M' WHERE recurrence IN ('mensuel', '1 × mois')");
        $this->addSql("UPDATE meeting SET recurrence = 'P2M' WHERE recurrence IN ('bimensuel', '1 × deux mois', '2 × mois')");
        $this->addSql("UPDATE meeting SET recurrence = 'P3M' WHERE recurrence IN ('trimestriel', '1 × trimestre', '3 × mois')");
        $this->addSql("UPDATE meeting SET recurrence = 'P6M' WHERE recurrence IN ('semestriel', '1 × semestre', '6 × mois')");
        $this->addSql("UPDATE meeting SET recurrence = 'P1Y' WHERE recurrence IN ('annuel', '1 × annee', '1 × year')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE meeting SET recurrence = 'hebdo' WHERE recurrence = 'P1W'");
        $this->addSql("UPDATE meeting SET recurrence = 'bi-hebdo' WHERE recurrence = 'P2W'");
        $this->addSql("UPDATE meeting SET recurrence = 'mensuel' WHERE recurrence = 'P1M'");
        $this->addSql("UPDATE meeting SET recurrence = 'bimensuel' WHERE recurrence = 'P2M'");
        $this->addSql("UPDATE meeting SET recurrence = 'trimestriel' WHERE recurrence = 'P3M'");
        $this->addSql("UPDATE meeting SET recurrence = 'semestriel' WHERE recurrence = 'P6M'");
        $this->addSql("UPDATE meeting SET recurrence = 'quotidien' WHERE recurrence = 'P1D'");
        $this->addSql("UPDATE meeting SET recurrence = 'annuel' WHERE recurrence = 'P1Y'");
    }
}
