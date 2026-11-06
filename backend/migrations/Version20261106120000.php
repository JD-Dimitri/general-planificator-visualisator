<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Schéma initial de GPV : comptes, domaines, sous-domaines imbriqués,
 * éléments et leurs étapes, accès, vacations, boîte de dépôt, séances de focus,
 * jetons de rafraîchissement.
 *
 * Écrite pour PostgreSQL. Pour repartir d'un schéma généré :
 * php bin/console doctrine:migrations:diff
 */
final class Version20261106120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial GPV';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform,
            'Cette migration vise PostgreSQL. Sur un autre moteur, régénère-la avec doctrine:migrations:diff.'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE app_user (
                id UUID NOT NULL,
                email VARCHAR(180) NOT NULL,
                roles JSON NOT NULL,
                password VARCHAR(255) NOT NULL,
                display_name VARCHAR(120) NOT NULL,
                plan VARCHAR(20) NOT NULL,
                phone VARCHAR(30) DEFAULT NULL,
                verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                settings JSON NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_app_user_email ON app_user (email)');

        $this->addSql(<<<'SQL'
            CREATE TABLE domain (
                id UUID NOT NULL,
                owner_id UUID NOT NULL,
                name VARCHAR(120) NOT NULL,
                hue SMALLINT NOT NULL,
                goal VARCHAR(255) NOT NULL,
                notes TEXT NOT NULL,
                position INT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_domain_owner_position ON domain (owner_id, position)');
        $this->addSql('ALTER TABLE domain ADD CONSTRAINT fk_domain_owner FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE');

        $this->addSql(<<<'SQL'
            CREATE TABLE sub_domain (
                id UUID NOT NULL,
                owner_id UUID NOT NULL,
                domain_id UUID NOT NULL,
                parent_id UUID DEFAULT NULL,
                name VARCHAR(120) NOT NULL,
                template VARCHAR(30) NOT NULL,
                notes TEXT NOT NULL,
                position INT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_sub_domain_parent ON sub_domain (parent_id)');
        $this->addSql('CREATE INDEX idx_sub_domain_domain ON sub_domain (domain_id, position)');
        $this->addSql('CREATE INDEX idx_sub_domain_owner ON sub_domain (owner_id)');
        $this->addSql('ALTER TABLE sub_domain ADD CONSTRAINT fk_sub_domain_owner FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sub_domain ADD CONSTRAINT fk_sub_domain_domain FOREIGN KEY (domain_id) REFERENCES domain (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sub_domain ADD CONSTRAINT fk_sub_domain_parent FOREIGN KEY (parent_id) REFERENCES sub_domain (id) ON DELETE CASCADE');

        $this->addSql(<<<'SQL'
            CREATE TABLE item (
                id UUID NOT NULL,
                owner_id UUID NOT NULL,
                domain_id UUID NOT NULL,
                sub_domain_id UUID DEFAULT NULL,
                title VARCHAR(255) NOT NULL,
                status VARCHAR(10) NOT NULL,
                priority VARCHAR(10) NOT NULL,
                due_date DATE DEFAULT NULL,
                due_time VARCHAR(5) DEFAULT NULL,
                recurrence VARCHAR(10) NOT NULL,
                recurrence_anchor_day SMALLINT DEFAULT NULL,
                manual_progress SMALLINT NOT NULL,
                fields JSON NOT NULL,
                notes TEXT NOT NULL,
                focus_minutes INT NOT NULL,
                completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                sample BOOLEAN NOT NULL,
                recurrence_spawned BOOLEAN NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_item_owner_due ON item (owner_id, due_date)');
        $this->addSql('CREATE INDEX idx_item_sub_domain ON item (sub_domain_id)');
        $this->addSql('CREATE INDEX idx_item_domain ON item (domain_id)');
        $this->addSql('ALTER TABLE item ADD CONSTRAINT fk_item_owner FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE item ADD CONSTRAINT fk_item_domain FOREIGN KEY (domain_id) REFERENCES domain (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE item ADD CONSTRAINT fk_item_sub_domain FOREIGN KEY (sub_domain_id) REFERENCES sub_domain (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE subtask (
                id UUID NOT NULL,
                item_id UUID NOT NULL,
                label VARCHAR(255) NOT NULL,
                done BOOLEAN NOT NULL,
                position INT NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_subtask_item ON subtask (item_id)');
        $this->addSql('ALTER TABLE subtask ADD CONSTRAINT fk_subtask_item FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE CASCADE');

        $this->addSql(<<<'SQL'
            CREATE TABLE access_link (
                id UUID NOT NULL,
                owner_id UUID NOT NULL,
                domain_id UUID DEFAULT NULL,
                sub_domain_id UUID DEFAULT NULL,
                label VARCHAR(120) NOT NULL,
                url VARCHAR(2048) NOT NULL,
                login VARCHAR(180) NOT NULL,
                position INT NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_access_link_owner ON access_link (owner_id)');
        $this->addSql('ALTER TABLE access_link ADD CONSTRAINT fk_access_link_owner FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE access_link ADD CONSTRAINT fk_access_link_domain FOREIGN KEY (domain_id) REFERENCES domain (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE access_link ADD CONSTRAINT fk_access_link_sub_domain FOREIGN KEY (sub_domain_id) REFERENCES sub_domain (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE shift (
                id UUID NOT NULL,
                owner_id UUID NOT NULL,
                date DATE NOT NULL,
                start_time VARCHAR(5) NOT NULL,
                end_time VARCHAR(5) NOT NULL,
                place VARCHAR(160) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_shift_owner_date ON shift (owner_id, date)');
        $this->addSql('ALTER TABLE shift ADD CONSTRAINT fk_shift_owner FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE');

        $this->addSql(<<<'SQL'
            CREATE TABLE inbox_entry (
                id UUID NOT NULL,
                owner_id UUID NOT NULL,
                suggested_domain_id UUID DEFAULT NULL,
                suggested_sub_domain_id UUID DEFAULT NULL,
                text VARCHAR(300) NOT NULL,
                source VARCHAR(180) NOT NULL,
                suggested_due_date DATE DEFAULT NULL,
                suggested_due_time VARCHAR(5) DEFAULT NULL,
                auto_classified BOOLEAN NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_inbox_entry_owner ON inbox_entry (owner_id)');
        $this->addSql('ALTER TABLE inbox_entry ADD CONSTRAINT fk_inbox_entry_owner FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE inbox_entry ADD CONSTRAINT fk_inbox_entry_domain FOREIGN KEY (suggested_domain_id) REFERENCES domain (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE inbox_entry ADD CONSTRAINT fk_inbox_entry_sub_domain FOREIGN KEY (suggested_sub_domain_id) REFERENCES sub_domain (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE focus_session (
                id UUID NOT NULL,
                owner_id UUID NOT NULL,
                domain_id UUID DEFAULT NULL,
                item_id UUID DEFAULT NULL,
                day DATE NOT NULL,
                minutes INT NOT NULL,
                mode VARCHAR(10) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_focus_owner_day ON focus_session (owner_id, day)');
        $this->addSql('ALTER TABLE focus_session ADD CONSTRAINT fk_focus_owner FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE focus_session ADD CONSTRAINT fk_focus_domain FOREIGN KEY (domain_id) REFERENCES domain (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE focus_session ADD CONSTRAINT fk_focus_item FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE refresh_token (
                id UUID NOT NULL,
                owner_id UUID NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                user_agent VARCHAR(255) NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_refresh_token_hash ON refresh_token (token_hash)');
        $this->addSql('CREATE INDEX idx_refresh_token_owner ON refresh_token (owner_id)');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT fk_refresh_token_owner FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE');

        $this->addSql(<<<'SQL'
            CREATE TABLE login_code (
                id UUID NOT NULL,
                user_id UUID NOT NULL,
                purpose VARCHAR(20) NOT NULL,
                channel VARCHAR(10) NOT NULL,
                masked_destination VARCHAR(180) NOT NULL,
                code_hash VARCHAR(64) NOT NULL,
                attempts INT NOT NULL,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                consumed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_login_code_user ON login_code (user_id)');
        $this->addSql('ALTER TABLE login_code ADD CONSTRAINT fk_login_code_user FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');

        $this->addSql(<<<'SQL'
            CREATE TABLE password_reset_token (
                id UUID NOT NULL,
                user_id UUID NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX uniq_password_reset_hash ON password_reset_token (token_hash)');
        $this->addSql('CREATE INDEX idx_password_reset_user ON password_reset_token (user_id)');
        $this->addSql('ALTER TABLE password_reset_token ADD CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        foreach (['password_reset_token', 'login_code', 'refresh_token', 'focus_session', 'inbox_entry', 'shift', 'access_link', 'subtask', 'item', 'sub_domain', 'domain', 'app_user'] as $table) {
            $this->addSql(\sprintf('DROP TABLE IF EXISTS %s CASCADE', $table));
        }
    }
}
