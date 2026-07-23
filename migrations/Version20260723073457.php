<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260723073457 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // PostgreSQL n'autorise pas LIKE sur une colonne json (nécessaire pour
        // le filtre par tag, US-03.3) : passage en texte simple. Un tableau de
        // tags vide devient NULL (voir Doctrine\DBAL\Types\SimpleArrayType),
        // d'où le DROP NOT NULL.
        $this->addSql('ALTER TABLE target ALTER tags TYPE TEXT USING tags::text');
        $this->addSql('ALTER TABLE target ALTER tags DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE target ALTER tags SET NOT NULL');
        $this->addSql('ALTER TABLE target ALTER tags TYPE JSON USING tags::json');
    }
}
