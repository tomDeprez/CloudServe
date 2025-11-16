<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251116000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create upload_queue table for async file uploads';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE upload_queue (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            parent_folder_id INT DEFAULT NULL,
            result_file_id INT DEFAULT NULL,
            filename VARCHAR(255) NOT NULL,
            temp_path VARCHAR(255) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            size BIGINT NOT NULL,
            hash VARCHAR(64) DEFAULT NULL,
            status VARCHAR(20) DEFAULT \'pending\' NOT NULL,
            error_message LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            processed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            progress INT DEFAULT 0 NOT NULL,
            INDEX IDX_UPLOAD_QUEUE_USER (user_id),
            INDEX IDX_UPLOAD_QUEUE_PARENT (parent_folder_id),
            INDEX IDX_UPLOAD_QUEUE_RESULT (result_file_id),
            INDEX IDX_UPLOAD_QUEUE_STATUS (status),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE upload_queue ADD CONSTRAINT FK_UPLOAD_QUEUE_USER FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE upload_queue ADD CONSTRAINT FK_UPLOAD_QUEUE_PARENT FOREIGN KEY (parent_folder_id) REFERENCES file (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE upload_queue ADD CONSTRAINT FK_UPLOAD_QUEUE_RESULT FOREIGN KEY (result_file_id) REFERENCES file (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE upload_queue DROP FOREIGN KEY FK_UPLOAD_QUEUE_USER');
        $this->addSql('ALTER TABLE upload_queue DROP FOREIGN KEY FK_UPLOAD_QUEUE_PARENT');
        $this->addSql('ALTER TABLE upload_queue DROP FOREIGN KEY FK_UPLOAD_QUEUE_RESULT');
        $this->addSql('DROP TABLE upload_queue');
    }
}
