<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250916200356 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE uploaded_dependency_file DROP user_id, DROP ci_upload_id, DROP scan_result, CHANGE file_path file_path VARCHAR(255) NOT NULL, CHANGE status status VARCHAR(255) NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE filename original_filename VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE uploaded_dependency_file ADD user_id VARCHAR(255) DEFAULT NULL, ADD ci_upload_id VARCHAR(255) DEFAULT NULL, ADD scan_result JSON DEFAULT NULL, CHANGE file_path file_path LONGTEXT DEFAULT NULL, CHANGE status status VARCHAR(50) NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE original_filename filename VARCHAR(255) NOT NULL');
    }
}
