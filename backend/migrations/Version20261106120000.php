<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Schéma initial de GPV : comptes, codes à usage unique, liens de
 * réinitialisation, domaines, sous-domaines imbriqués, éléments et leurs
 * étapes, accès, vacations, boîte de dépôt, séances de focus, jetons.
 *
 * Elle tourne sur MySQL/MariaDB comme sur PostgreSQL : seuls quelques types
 * changent d'un moteur à l'autre, et ils sont rassemblés en haut de `up()`.
 * MySQL est celui des hébergements mutualisés en cPanel, PostgreSQL celui des
 * plateformes managées.
 *
 * Pour repartir d'un schéma généré :
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
        $mysql = $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform;

        // Les types qui diffèrent. Tout le reste s'écrit pareil des deux côtés.
        $uuid = $mysql ? 'CHAR(36)' : 'UUID';
        $stamp = $mysql ? 'DATETIME' : 'TIMESTAMP(0) WITHOUT TIME ZONE';
        $text = $mysql ? 'LONGTEXT' : 'TEXT';
        $bool = $mysql ? 'TINYINT(1)' : 'BOOLEAN';
        $opts = $mysql ? ' ENGINE = InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`' : '';

        // Les index se déclarent dans le CREATE TABLE sur MySQL, et seulement
        // après coup sur PostgreSQL : ce filtre retire les lignes INDEX pour lui,
        // qui les retrouve dans postgresIndexes().
        $table = static function (string $sql) use ($mysql, $opts): string {
            if (!$mysql) {
                $sql = preg_replace('/^[ \t]*(?:UNIQUE )?INDEX [^\n]*\n/m', '', $sql);
            }

            return $sql.$opts;
        };

        $this->addSql($table("CREATE TABLE app_user (
            id $uuid NOT NULL,
            email VARCHAR(180) NOT NULL,
            roles JSON NOT NULL,
            password VARCHAR(255) NOT NULL,
            display_name VARCHAR(120) NOT NULL,
            plan VARCHAR(20) NOT NULL,
            phone VARCHAR(30) DEFAULT NULL,
            verified_at $stamp DEFAULT NULL,
            settings JSON NOT NULL,
            created_at $stamp NOT NULL,
            PRIMARY KEY (id)
        )"));
        $this->addSql('CREATE UNIQUE INDEX uniq_app_user_email ON app_user (email)');

        $this->addSql($table("CREATE TABLE login_code (
            id $uuid NOT NULL,
            user_id $uuid NOT NULL,
            purpose VARCHAR(20) NOT NULL,
            channel VARCHAR(10) NOT NULL,
            masked_destination VARCHAR(180) NOT NULL,
            code_hash VARCHAR(64) NOT NULL,
            attempts INT NOT NULL,
            expires_at $stamp NOT NULL,
            created_at $stamp NOT NULL,
            consumed_at $stamp DEFAULT NULL,
            INDEX idx_login_code_user (user_id),
            PRIMARY KEY (id)
        )"));

        $this->addSql($table("CREATE TABLE password_reset_token (
            id $uuid NOT NULL,
            user_id $uuid NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at $stamp NOT NULL,
            created_at $stamp NOT NULL,
            used_at $stamp DEFAULT NULL,
            UNIQUE INDEX uniq_password_reset_hash (token_hash),
            INDEX idx_password_reset_user (user_id),
            PRIMARY KEY (id)
        )"));

        $this->addSql($table("CREATE TABLE refresh_token (
            id $uuid NOT NULL,
            owner_id $uuid NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at $stamp NOT NULL,
            created_at $stamp NOT NULL,
            revoked_at $stamp DEFAULT NULL,
            user_agent VARCHAR(255) NOT NULL,
            UNIQUE INDEX uniq_refresh_token_hash (token_hash),
            INDEX idx_refresh_token_owner (owner_id),
            PRIMARY KEY (id)
        )"));

        $this->addSql($table("CREATE TABLE domain (
            id $uuid NOT NULL,
            owner_id $uuid NOT NULL,
            name VARCHAR(120) NOT NULL,
            hue SMALLINT NOT NULL,
            goal VARCHAR(255) NOT NULL,
            notes $text NOT NULL,
            position INT NOT NULL,
            created_at $stamp NOT NULL,
            updated_at $stamp NOT NULL,
            INDEX idx_domain_owner_position (owner_id, position),
            PRIMARY KEY (id)
        )"));

        $this->addSql($table("CREATE TABLE sub_domain (
            id $uuid NOT NULL,
            owner_id $uuid NOT NULL,
            domain_id $uuid NOT NULL,
            parent_id $uuid DEFAULT NULL,
            name VARCHAR(120) NOT NULL,
            template VARCHAR(30) NOT NULL,
            notes $text NOT NULL,
            position INT NOT NULL,
            created_at $stamp NOT NULL,
            updated_at $stamp NOT NULL,
            INDEX idx_sub_domain_owner (owner_id),
            INDEX idx_sub_domain_parent (parent_id),
            INDEX idx_sub_domain_domain (domain_id, position),
            PRIMARY KEY (id)
        )"));

        $this->addSql($table("CREATE TABLE item (
            id $uuid NOT NULL,
            owner_id $uuid NOT NULL,
            domain_id $uuid NOT NULL,
            sub_domain_id $uuid DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            status VARCHAR(10) NOT NULL,
            priority VARCHAR(10) NOT NULL,
            due_date DATE DEFAULT NULL,
            due_time VARCHAR(5) DEFAULT NULL,
            recurrence VARCHAR(10) NOT NULL,
            recurrence_anchor_day SMALLINT DEFAULT NULL,
            manual_progress SMALLINT NOT NULL,
            fields JSON NOT NULL,
            notes $text NOT NULL,
            focus_minutes INT NOT NULL,
            completed_at $stamp DEFAULT NULL,
            sample $bool NOT NULL,
            recurrence_spawned $bool NOT NULL,
            created_at $stamp NOT NULL,
            updated_at $stamp NOT NULL,
            INDEX idx_item_owner_due (owner_id, due_date),
            INDEX idx_item_domain (domain_id),
            INDEX idx_item_sub_domain (sub_domain_id),
            PRIMARY KEY (id)
        )"));

        $this->addSql($table("CREATE TABLE subtask (
            id $uuid NOT NULL,
            item_id $uuid NOT NULL,
            label VARCHAR(255) NOT NULL,
            done $bool NOT NULL,
            position INT NOT NULL,
            INDEX idx_subtask_item (item_id),
            PRIMARY KEY (id)
        )"));

        $this->addSql($table("CREATE TABLE access_link (
            id $uuid NOT NULL,
            owner_id $uuid NOT NULL,
            domain_id $uuid DEFAULT NULL,
            sub_domain_id $uuid DEFAULT NULL,
            label VARCHAR(120) NOT NULL,
            url VARCHAR(2048) NOT NULL,
            login VARCHAR(180) NOT NULL,
            position INT NOT NULL,
            created_at $stamp NOT NULL,
            updated_at $stamp NOT NULL,
            INDEX idx_access_link_owner (owner_id),
            INDEX idx_access_link_domain (domain_id),
            INDEX idx_access_link_sub_domain (sub_domain_id),
            PRIMARY KEY (id)
        )"));

        $this->addSql($table("CREATE TABLE shift (
            id $uuid NOT NULL,
            owner_id $uuid NOT NULL,
            date DATE NOT NULL,
            start_time VARCHAR(5) NOT NULL,
            end_time VARCHAR(5) NOT NULL,
            place VARCHAR(160) NOT NULL,
            created_at $stamp NOT NULL,
            updated_at $stamp NOT NULL,
            INDEX idx_shift_owner_date (owner_id, date),
            PRIMARY KEY (id)
        )"));

        $this->addSql($table("CREATE TABLE inbox_entry (
            id $uuid NOT NULL,
            owner_id $uuid NOT NULL,
            suggested_domain_id $uuid DEFAULT NULL,
            suggested_sub_domain_id $uuid DEFAULT NULL,
            text VARCHAR(300) NOT NULL,
            source VARCHAR(180) NOT NULL,
            suggested_due_date DATE DEFAULT NULL,
            suggested_due_time VARCHAR(5) DEFAULT NULL,
            auto_classified $bool NOT NULL,
            created_at $stamp NOT NULL,
            updated_at $stamp NOT NULL,
            INDEX idx_inbox_entry_owner (owner_id),
            INDEX idx_inbox_entry_domain (suggested_domain_id),
            INDEX idx_inbox_entry_sub_domain (suggested_sub_domain_id),
            PRIMARY KEY (id)
        )"));

        $this->addSql($table("CREATE TABLE focus_session (
            id $uuid NOT NULL,
            owner_id $uuid NOT NULL,
            domain_id $uuid DEFAULT NULL,
            item_id $uuid DEFAULT NULL,
            day DATE NOT NULL,
            minutes INT NOT NULL,
            mode VARCHAR(10) NOT NULL,
            created_at $stamp NOT NULL,
            updated_at $stamp NOT NULL,
            INDEX idx_focus_owner_day (owner_id, day),
            INDEX idx_focus_domain (domain_id),
            INDEX idx_focus_item (item_id),
            PRIMARY KEY (id)
        )"));

        // PostgreSQL n'accepte pas INDEX dans CREATE TABLE : on les recrée après.
        if (!$mysql) {
            foreach ($this->postgresIndexes() as $sql) {
                $this->addSql($sql);
            }
        }

        foreach ($this->foreignKeys() as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $mysql = $this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform;
        $tables = ['focus_session', 'inbox_entry', 'shift', 'access_link', 'subtask', 'item',
            'sub_domain', 'domain', 'refresh_token', 'password_reset_token', 'login_code', 'app_user'];

        if ($mysql) {
            $this->addSql('SET FOREIGN_KEY_CHECKS = 0');
        }

        foreach ($tables as $table) {
            $this->addSql(\sprintf('DROP TABLE IF EXISTS %s%s', $table, $mysql ? '' : ' CASCADE'));
        }

        if ($mysql) {
            $this->addSql('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    /** @return list<string> */
    private function postgresIndexes(): array
    {
        return [
            'CREATE INDEX idx_login_code_user ON login_code (user_id)',
            'CREATE UNIQUE INDEX uniq_password_reset_hash ON password_reset_token (token_hash)',
            'CREATE INDEX idx_password_reset_user ON password_reset_token (user_id)',
            'CREATE UNIQUE INDEX uniq_refresh_token_hash ON refresh_token (token_hash)',
            'CREATE INDEX idx_refresh_token_owner ON refresh_token (owner_id)',
            'CREATE INDEX idx_domain_owner_position ON domain (owner_id, position)',
            'CREATE INDEX idx_sub_domain_owner ON sub_domain (owner_id)',
            'CREATE INDEX idx_sub_domain_parent ON sub_domain (parent_id)',
            'CREATE INDEX idx_sub_domain_domain ON sub_domain (domain_id, position)',
            'CREATE INDEX idx_item_owner_due ON item (owner_id, due_date)',
            'CREATE INDEX idx_item_domain ON item (domain_id)',
            'CREATE INDEX idx_item_sub_domain ON item (sub_domain_id)',
            'CREATE INDEX idx_subtask_item ON subtask (item_id)',
            'CREATE INDEX idx_access_link_owner ON access_link (owner_id)',
            'CREATE INDEX idx_access_link_domain ON access_link (domain_id)',
            'CREATE INDEX idx_access_link_sub_domain ON access_link (sub_domain_id)',
            'CREATE INDEX idx_shift_owner_date ON shift (owner_id, date)',
            'CREATE INDEX idx_inbox_entry_owner ON inbox_entry (owner_id)',
            'CREATE INDEX idx_inbox_entry_domain ON inbox_entry (suggested_domain_id)',
            'CREATE INDEX idx_inbox_entry_sub_domain ON inbox_entry (suggested_sub_domain_id)',
            'CREATE INDEX idx_focus_owner_day ON focus_session (owner_id, day)',
            'CREATE INDEX idx_focus_domain ON focus_session (domain_id)',
            'CREATE INDEX idx_focus_item ON focus_session (item_id)',
        ];
    }

    /** @return list<string> */
    private function foreignKeys(): array
    {
        $fk = static fn (string $table, string $name, string $column, string $target, string $onDelete) => \sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (id) ON DELETE %s',
            $table, $name, $column, $target, $onDelete
        );

        return [
            $fk('login_code', 'fk_login_code_user', 'user_id', 'app_user', 'CASCADE'),
            $fk('password_reset_token', 'fk_password_reset_user', 'user_id', 'app_user', 'CASCADE'),
            $fk('refresh_token', 'fk_refresh_token_owner', 'owner_id', 'app_user', 'CASCADE'),
            $fk('domain', 'fk_domain_owner', 'owner_id', 'app_user', 'CASCADE'),
            $fk('sub_domain', 'fk_sub_domain_owner', 'owner_id', 'app_user', 'CASCADE'),
            $fk('sub_domain', 'fk_sub_domain_domain', 'domain_id', 'domain', 'CASCADE'),
            $fk('sub_domain', 'fk_sub_domain_parent', 'parent_id', 'sub_domain', 'CASCADE'),
            $fk('item', 'fk_item_owner', 'owner_id', 'app_user', 'CASCADE'),
            $fk('item', 'fk_item_domain', 'domain_id', 'domain', 'CASCADE'),
            $fk('item', 'fk_item_sub_domain', 'sub_domain_id', 'sub_domain', 'SET NULL'),
            $fk('subtask', 'fk_subtask_item', 'item_id', 'item', 'CASCADE'),
            $fk('access_link', 'fk_access_link_owner', 'owner_id', 'app_user', 'CASCADE'),
            $fk('access_link', 'fk_access_link_domain', 'domain_id', 'domain', 'CASCADE'),
            $fk('access_link', 'fk_access_link_sub_domain', 'sub_domain_id', 'sub_domain', 'SET NULL'),
            $fk('shift', 'fk_shift_owner', 'owner_id', 'app_user', 'CASCADE'),
            $fk('inbox_entry', 'fk_inbox_entry_owner', 'owner_id', 'app_user', 'CASCADE'),
            $fk('inbox_entry', 'fk_inbox_entry_domain', 'suggested_domain_id', 'domain', 'SET NULL'),
            $fk('inbox_entry', 'fk_inbox_entry_sub_domain', 'suggested_sub_domain_id', 'sub_domain', 'SET NULL'),
            $fk('focus_session', 'fk_focus_owner', 'owner_id', 'app_user', 'CASCADE'),
            $fk('focus_session', 'fk_focus_domain', 'domain_id', 'domain', 'SET NULL'),
            $fk('focus_session', 'fk_focus_item', 'item_id', 'item', 'SET NULL'),
        ];
    }
}
