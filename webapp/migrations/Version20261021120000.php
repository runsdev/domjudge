<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add test runs, code that teams run on a judgehost with input of their own';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE test_run (testrunid INT UNSIGNED AUTO_INCREMENT NOT NULL COMMENT \'Test run ID\', filename VARCHAR(255) NOT NULL COMMENT \'Filename of the code\', source LONGBLOB NOT NULL COMMENT \'The code\', input LONGBLOB NOT NULL COMMENT \'Input for the code\', compile_success TINYINT DEFAULT NULL COMMENT \'Whether the code compiled, NULL if not compiled yet\', output_compile LONGBLOB DEFAULT NULL COMMENT \'Output of compiling the code\', runresult VARCHAR(32) DEFAULT NULL COMMENT \'Result of running the code, NULL if not run yet\', runtime DOUBLE PRECISION DEFAULT NULL COMMENT \'Running time of the code\', output_run LONGBLOB DEFAULT NULL COMMENT \'Output of running the code\', output_error LONGBLOB DEFAULT NULL COMMENT \'Standard error output of the code\', metadata LONGBLOB DEFAULT NULL COMMENT \'Judging metadata of the run\', teamid INT UNSIGNED NOT NULL COMMENT \'Team ID\', langid INT UNSIGNED NOT NULL COMMENT \'Language ID\', judgetaskid INT UNSIGNED DEFAULT NULL COMMENT \'Judgetask ID\', INDEX teamid (teamid), INDEX langid (langid), INDEX judgetaskid (judgetaskid), PRIMARY KEY (testrunid)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'Code run by teams with input of their own\'');
        $this->addSql('ALTER TABLE test_run ADD CONSTRAINT FK_290B78074DD6ABF3 FOREIGN KEY (teamid) REFERENCES team (teamid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE test_run ADD CONSTRAINT FK_290B78072271845 FOREIGN KEY (langid) REFERENCES language (langid) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE test_run ADD CONSTRAINT FK_290B78073CBA64F2 FOREIGN KEY (judgetaskid) REFERENCES judgetask (judgetaskid) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE judgetask CHANGE type type ENUM(\'config_check\', \'debug_info\', \'generic_task\', \'judging_run\', \'prefetch\', \'test_run\') DEFAULT \'judging_run\' NOT NULL COMMENT \'Type of the judge task.\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM judgetask WHERE type = \'test_run\'');
        $this->addSql('ALTER TABLE judgetask CHANGE type type ENUM(\'config_check\', \'debug_info\', \'generic_task\', \'judging_run\', \'prefetch\') DEFAULT \'judging_run\' NOT NULL COMMENT \'Type of the judge task.\'');
        $this->addSql('DROP TABLE test_run');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
