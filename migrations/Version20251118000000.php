<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajouter original_hash pour détecter les doublons même après compression
 */
final class Version20251118000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajouter colonne original_hash à la table file pour double détection de doublons';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE file ADD original_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_8C9F3610ORIG_HASH ON file (original_hash)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_8C9F3610ORIG_HASH ON file');
        $this->addSql('ALTER TABLE file DROP original_hash');
    }
}
