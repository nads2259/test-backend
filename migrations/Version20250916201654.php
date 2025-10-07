<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250916201654 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE uploaded_dependency_file ADD debricked_upload_id VARCHAR(255) DEFAULT NULL, ADD vulnerability_count INT DEFAULT NULL, ADD scan_result_payload JSON DEFAULT NULL, ADD error_message LONGTEXT DEFAULT NULL, CHANGE status status VARCHAR(50) NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE uploaded_dependency_file DROP debricked_upload_id, DROP vulnerability_count, DROP scan_result_payload, DROP error_message, CHANGE status status VARCHAR(255) NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL');
    }
}
