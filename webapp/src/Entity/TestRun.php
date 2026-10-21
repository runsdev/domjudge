<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Code that a team runs on a judgehost with input of their own, from the code editor.
 */
#[ORM\Entity]
#[ORM\Table(options: [
    'collation' => 'utf8mb4_unicode_ci',
    'charset' => 'utf8mb4',
    'comment' => 'Code run by teams with input of their own',
])]
#[ORM\Index(name: 'teamid', columns: ['teamid'])]
#[ORM\Index(name: 'langid', columns: ['langid'])]
#[ORM\Index(name: 'judgetaskid', columns: ['judgetaskid'])]
class TestRun
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['comment' => 'Test run ID', 'unsigned' => true])]
    private int $testrunid;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'teamid', referencedColumnName: 'teamid', nullable: false, onDelete: 'CASCADE')]
    private Team $team;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'langid', referencedColumnName: 'langid', nullable: false, onDelete: 'CASCADE')]
    private Language $language;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'judgetaskid', referencedColumnName: 'judgetaskid', onDelete: 'SET NULL')]
    private ?JudgeTask $judgeTask = null;

    #[ORM\Column(length: 255, options: ['comment' => 'Filename of the code'])]
    private string $filename;

    #[ORM\Column(type: 'blobtext', options: ['comment' => 'The code'])]
    private string $source;

    #[ORM\Column(type: 'blobtext', options: ['comment' => 'Input for the code'])]
    private string $input;

    #[ORM\Column(nullable: true, options: ['comment' => 'Whether the code compiled, NULL if not compiled yet'])]
    private ?bool $compileSuccess = null;

    #[ORM\Column(type: 'blobtext', nullable: true, options: ['comment' => 'Output of compiling the code'])]
    private ?string $outputCompile = null;

    #[ORM\Column(length: 32, nullable: true, options: ['comment' => 'Result of running the code, NULL if not run yet'])]
    private ?string $runresult = null;

    #[ORM\Column(nullable: true, options: ['comment' => 'Running time of the code'])]
    private ?float $runtime = null;

    #[ORM\Column(type: 'blobtext', nullable: true, options: ['comment' => 'Output of running the code'])]
    private ?string $outputRun = null;

    #[ORM\Column(type: 'blobtext', nullable: true, options: ['comment' => 'Standard error output of the code'])]
    private ?string $outputError = null;

    #[ORM\Column(type: 'blobtext', nullable: true, options: ['comment' => 'Judging metadata of the run'])]
    private ?string $metadata = null;

    public function getTestrunid(): int
    {
        return $this->testrunid;
    }

    public function getTeam(): Team
    {
        return $this->team;
    }

    public function setTeam(Team $team): TestRun
    {
        $this->team = $team;
        return $this;
    }

    public function getLanguage(): Language
    {
        return $this->language;
    }

    public function setLanguage(Language $language): TestRun
    {
        $this->language = $language;
        return $this;
    }

    public function getJudgeTask(): ?JudgeTask
    {
        return $this->judgeTask;
    }

    public function setJudgeTask(?JudgeTask $judgeTask): TestRun
    {
        $this->judgeTask = $judgeTask;
        return $this;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): TestRun
    {
        $this->filename = $filename;
        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): TestRun
    {
        $this->source = $source;
        return $this;
    }

    public function getInput(): string
    {
        return $this->input;
    }

    public function setInput(string $input): TestRun
    {
        $this->input = $input;
        return $this;
    }

    public function getCompileSuccess(): ?bool
    {
        return $this->compileSuccess;
    }

    public function setCompileSuccess(?bool $compileSuccess): TestRun
    {
        $this->compileSuccess = $compileSuccess;
        return $this;
    }

    public function getOutputCompile(): ?string
    {
        return $this->outputCompile;
    }

    public function setOutputCompile(?string $outputCompile): TestRun
    {
        $this->outputCompile = $outputCompile;
        return $this;
    }

    public function getRunresult(): ?string
    {
        return $this->runresult;
    }

    public function setRunresult(?string $runresult): TestRun
    {
        $this->runresult = $runresult;
        return $this;
    }

    public function getRuntime(): ?float
    {
        return $this->runtime;
    }

    public function setRuntime(?float $runtime): TestRun
    {
        $this->runtime = $runtime;
        return $this;
    }

    public function getOutputRun(): ?string
    {
        return $this->outputRun;
    }

    public function setOutputRun(?string $outputRun): TestRun
    {
        $this->outputRun = $outputRun;
        return $this;
    }

    public function getOutputError(): ?string
    {
        return $this->outputError;
    }

    public function setOutputError(?string $outputError): TestRun
    {
        $this->outputError = $outputError;
        return $this;
    }

    public function getMetadata(): ?string
    {
        return $this->metadata;
    }

    public function setMetadata(?string $metadata): TestRun
    {
        $this->metadata = $metadata;
        return $this;
    }
}
