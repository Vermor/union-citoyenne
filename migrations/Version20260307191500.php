<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260307191500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create supporter table for email-based charter adhesion';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE supporter (id SERIAL NOT NULL, email VARCHAR(180) NOT NULL, agrees_to_charter BOOLEAN NOT NULL, accepts_future_contact BOOLEAN NOT NULL, is_confirmed BOOLEAN NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, confirmation_sent_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, confirmed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ip_hash VARCHAR(64) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_supporter_email ON supporter (email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE supporter');
    }
}
