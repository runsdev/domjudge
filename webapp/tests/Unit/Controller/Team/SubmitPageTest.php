<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Team;

use App\Doctrine\DBAL\Types\JudgeTaskType;
use App\Entity\Contest;
use App\Entity\Executable;
use App\Entity\Judgehost;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\TestRun;
use App\Service\ConfigurationService;
use App\Tests\Unit\BaseTestCase;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;

class SubmitPageTest extends BaseTestCase
{
    protected array $roles = ['team'];

    public function testSubmitPageDefaultShowsUploadForm(): void
    {
        $this->verifyPageResponse('GET', '/team/submit', 200);
        $content = $this->client->getResponse()->getContent();

        // Default config includes 'upload', so upload form should be present
        static::assertStringContainsString('submit_problem', $content);
    }

    public function testSubmitPageUploadOnlyHasNoTabs(): void
    {
        $this->withChangedConfiguration('submit_methods', ['upload'], function (): void {
            $this->verifyPageResponse('GET', '/team/submit', 200);
            $crawler = $this->getCurrentCrawler();

            // No tab UI should be present
            static::assertCount(0, $crawler->filter('#upload-tab-page'));
            static::assertCount(0, $crawler->filter('#paste-tab-page'));
        });
    }

    public function testSubmitPagePasteOnlyShowsPasteForm(): void
    {
        $this->withChangedConfiguration('submit_methods', ['paste'], function (): void {
            $this->verifyPageResponse('GET', '/team/submit', 200);
            $crawler = $this->getCurrentCrawler();

            // Paste form should be present
            static::assertCount(1, $crawler->filter('form[name="submit_problem_paste"]'));
            // Upload form should not be present
            static::assertCount(0, $crawler->filter('form[name="submit_problem"]'));
            // No file upload input
            static::assertCount(0, $crawler->filter('#submit_problem_code'));
        });
    }

    public function testSubmitPageBothMethodsShowsTabs(): void
    {
        $this->withChangedConfiguration('submit_methods', ['upload', 'paste'], function (): void {
            $this->verifyPageResponse('GET', '/team/submit', 200);
            $content = $this->client->getResponse()->getContent();

            static::assertStringContainsString('upload-tab-page', $content);
            static::assertStringContainsString('paste-tab-page', $content);
            static::assertStringContainsString('Upload File', $content);
            static::assertStringContainsString('Paste Code', $content);
        });
    }

    public function testSubmitPageBothMethodsHasUploadAndPasteForms(): void
    {
        $this->withChangedConfiguration('submit_methods', ['upload', 'paste'], function (): void {
            $this->verifyPageResponse('GET', '/team/submit', 200);
            $content = $this->client->getResponse()->getContent();

            // Upload form
            static::assertStringContainsString('submit_problem', $content);
            // Paste form
            static::assertStringContainsString('submit_problem_paste', $content);
        });
    }

    public function testSubmitModalOnlyHasUploadForm(): void
    {
        $this->withChangedConfiguration('submit_methods', ['upload', 'paste'], function (): void {
            // AJAX request returns the modal
            $this->verifyPageResponse('GET', '/team/submit', 200, null, true);
            $content = $this->client->getResponse()->getContent();

            // Modal should have upload form
            static::assertStringContainsString('submit_problem', $content);
            // Modal should NOT have paste form (paste is full-page only)
            static::assertStringNotContainsString('submit_problem_paste_code_content', $content);
        });
    }

    public function testSubmitModalPasteOnlyShowsFallbackLink(): void
    {
        $this->withChangedConfiguration('submit_methods', ['paste'], function (): void {
            $this->verifyPageResponse('GET', '/team/submit', 200, null, true);
            $content = $this->client->getResponse()->getContent();

            // Should show fallback message linking to the full page
            static::assertStringContainsString('full submit page', $content);
        });
    }

    public function testSubmitModalBothMethodsShowsPasteTabLink(): void
    {
        $this->withChangedConfiguration('submit_methods', ['upload', 'paste'], function (): void {
            $this->verifyPageResponse('GET', '/team/submit', 200, null, true);
            $content = $this->client->getResponse()->getContent();

            // Modal should have a tab linking to the paste page
            static::assertStringContainsString('Paste Code', $content);
            static::assertStringContainsString('tab=paste', $content);
        });
    }

    public function testMenuShowsUploadModalWhenUploadEnabled(): void
    {
        $this->withChangedConfiguration('submit_methods', ['upload'], function (): void {
            $this->verifyPageResponse('GET', '/team', 200);
            $content = $this->client->getResponse()->getContent();

            static::assertStringContainsString('data-ajax-modal', $content);
        });
    }

    public function testMenuShowsBothSubmitButtonsWhenBothMethodsEnabled(): void
    {
        $this->withChangedConfiguration('submit_methods', ['upload', 'paste'], function (): void {
            $this->verifyPageResponse('GET', '/team', 200);
            $crawler = $this->getCurrentCrawler();

            // Modal button (default, visible)
            static::assertCount(1, $crawler->filter('#submit-btn-modal'));
            // Paste direct link (hidden by default, shown via JS when cookie is set)
            static::assertCount(1, $crawler->filter('#submit-btn-paste'));
        });
    }

    public function testMenuShowsDirectLinkWhenPasteOnly(): void
    {
        $this->withChangedConfiguration('submit_methods', ['paste'], function (): void {
            $this->verifyPageResponse('GET', '/team', 200);
            $content = $this->client->getResponse()->getContent();

            // Should link directly to submit page, not use modal
            $crawler = $this->getCurrentCrawler();
            $submitLinks = $crawler->filter('a[href*="team/submit"]');
            static::assertGreaterThan(0, $submitLinks->count());

            // Should NOT have modal trigger
            $modalLinks = $crawler->filter('a[data-ajax-modal][href*="team/submit"]');
            static::assertCount(0, $modalLinks);
        });
    }

    public function testPasteFormOffersAllLanguagesForProblemWithLanguages(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hello = $em->getRepository(Problem::class)->findOneBy(['externalid' => 'hello']);
        $hello->addLanguage($em->getRepository(Language::class)->findOneBy(['externalid' => 'cpp']));
        $em->flush();

        $this->withChangedConfiguration('submit_methods', ['upload', 'paste'], function () use ($hello): void {
            $this->verifyPageResponse('GET', '/team/submit/' . $hello->getProbid(), 200);
            $crawler = $this->getCurrentCrawler();
            // The upload form is for this problem only, the code editor can switch problems.
            static::assertSame(['', 'cpp'], $crawler->filter('#submit_problem_language option')->extract(['value']));
            static::assertContains('java', $crawler->filter('#submit_problem_paste_language option')->extract(['value']));
        });
    }

    public static function pasteSubmissionProvider(): \Generator
    {
        yield 'C++ uses the problem name' => ['cpp', "int main() { return 0; }\n", 'A.cpp'];
        yield 'Java uses the public class' => ['java', "import java.util.*;\n\npublic final class Main {\n    public static void main(String[] args) {}\n}\n", 'Main.java'];
        yield 'Java without public class' => ['java', "class Main {\n    public static void main(String[] args) {}\n}\n", 'A.java'];
        yield 'Java with annotation and modifiers' => ['java', "@SuppressWarnings(\"unchecked\") final public class Sol {\n    public static void main(String[] args) {}\n}\n", 'Sol.java'];
        yield 'Python uses the problem name' => ['python3', "print('hello world')\n", 'A.py'];
        yield 'Browser CRLF line endings become LF' => ['cpp', "int main() {\r\n    return 0;\r\n}\r\n", 'A.cpp', "int main() {\n    return 0;\n}\n"];
    }

    #[DataProvider('pasteSubmissionProvider')]
    public function testPasteSubmission(string $language, string $code, string $filename, ?string $stored = null): void
    {
        $this->withChangedConfiguration('submit_methods', ['paste'], function () use ($language, $code, $filename, $stored): void {
            $this->verifyPageResponse('GET', '/team/submit', 200);
            $this->client->submitForm('Submit', [
                'submit_problem_paste[problem]' => 'hello',
                'submit_problem_paste[language]' => $language,
                'submit_problem_paste[code_content]' => $code,
            ]);
            static::assertTrue($this->client->getResponse()->isRedirect());

            /** @var Submission $submission */
            $submission = self::getContainer()->get(EntityManagerInterface::class)
                ->getRepository(Submission::class)->findOneBy([], ['submitid' => 'DESC']);
            static::assertSame('hello', $submission->getProblem()->getExternalid());
            static::assertCount(1, $submission->getFiles());
            static::assertSame($filename, $submission->getFiles()->first()->getFilename());
            static::assertSame($stored ?? $code, $submission->getFiles()->first()->getSourcecode());
        });
    }

    public function testPastePageShowsStatementsNextToEditor(): void
    {
        $this->withChangedConfiguration('submit_methods', ['paste'], function (): void {
            $this->verifyPageResponse('GET', '/team/submit', 200);
            $crawler = $this->getCurrentCrawler();

            static::assertCount(1, $crawler->filter('#paste-statement-toggle'));
            $urls = json_decode($crawler->filter('#paste-statement')->attr('data-statement-urls'), true);
            static::assertContains(['hello', '/team/problems/hello/statement#navpanes=0'], $urls, json_encode($urls));
            static::assertStringStartsWith('domjudge-draft/',
                $crawler->filter('form[name="submit_problem_paste"]')->attr('data-draft-key'));
        });
    }

    public function testPastePageFallsBackToContestProblemset(): void
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->getRepository(Contest::class)->findOneBy(['shortname' => 'demo'])->setContestProblemsetType('pdf');
        $em->flush();

        $this->withChangedConfiguration('submit_methods', ['paste'], function (): void {
            $this->verifyPageResponse('GET', '/team/submit', 200);
            $urls = json_decode($this->getCurrentCrawler()->filter('#paste-statement')->attr('data-statement-urls'), true);
            static::assertContains(['', '/team/problemset#navpanes=0'], $urls, json_encode($urls));
            static::assertContains(['hello', '/team/problems/hello/statement#navpanes=0'], $urls);
        });
    }

    public function testEditorPagesGetTheirContentSecurityPolicy(): void
    {
        $this->verifyPageResponse('GET', '/team/submit', 200);
        static::assertStringContainsString("'wasm-unsafe-eval'",
            $this->client->getResponse()->headers->get('Content-Security-Policy'));

        $this->client->request('GET', '/team/problems/hello/statement');
        static::assertStringContainsString("frame-ancestors 'self'",
            $this->client->getResponse()->headers->get('Content-Security-Policy'));

        $this->verifyPageResponse('GET', '/team', 200);
        $csp = $this->client->getResponse()->headers->get('Content-Security-Policy');
        static::assertStringNotContainsString('frame-ancestors', $csp);
        static::assertStringNotContainsString('wasm-unsafe-eval', $csp);
    }

    public function testProblemSubmitButtonOpensEditorWithoutUpload(): void
    {
        $this->withChangedConfiguration('submit_methods', ['paste'], function (): void {
            $this->verifyPageResponse('GET', '/team/problems', 200);
            $links = $this->getCurrentCrawler()->filter('.problem-list a[href*="/team/submit/"]');
            static::assertGreaterThan(0, $links->count());
            static::assertCount(0, $links->filter('[data-ajax-modal]'));
            static::assertSame('Solve', $links->first()->text());
        });
    }

    public function testLockdownOnlyAllowsTheCodeEditor(): void
    {
        $this->withChangedConfiguration('submit_methods', ['upload', 'paste'], function (): void {
            $this->withChangedConfiguration('team_lockdown', true, function (): void {
                $this->verifyPageResponse('GET', '/team/submit', 200);
                $crawler = $this->getCurrentCrawler();
                static::assertCount(0, $crawler->filter('form[name="submit_problem"]'));
                static::assertCount(1, $crawler->filter('form[name="submit_problem_paste"]'));

                $this->verifyPageResponse('GET', '/team/problems', 200);
                static::assertCount(0, $this->getCurrentCrawler()->filter('[data-ajax-modal-after="initSubmitModal"]'));
            });
        });
    }

    public function testEditorHasRunButton(): void
    {
        $this->withChangedConfiguration('submit_methods', ['paste'], function (): void {
            $this->verifyPageResponse('GET', '/team/submit', 200);
            static::assertSame('/team/run', $this->getCurrentCrawler()->filter('#paste-run-button')->attr('data-url'));

            $this->withChangedConfiguration('allow_test_runs', false, function (): void {
                $this->verifyPageResponse('GET', '/team/submit', 200);
                static::assertCount(0, $this->getCurrentCrawler()->filter('#paste-run-button'));
                $this->client->request('POST', '/team/run', ['problem' => 'hello', 'language' => 'cpp', 'code' => 'int main() {}']);
                static::assertResponseStatusCodeSame(404);
            });
        });
    }

    /**
     * @param array<string, string> $request
     */
    #[DataProvider('invalidRunProvider')]
    public function testRunNeedsProblemAndLanguage(array $request, string $error): void
    {
        $this->client->request('POST', '/team/run', $request + ['code' => 'int main() {}']);
        static::assertResponseStatusCodeSame(400);
        static::assertSame(['error' => $error], json_decode($this->client->getResponse()->getContent(), true));
    }

    public static function invalidRunProvider(): Generator
    {
        yield 'no problem' => [['language' => 'cpp'], 'Select a problem to run the code with its limits.'];
        yield 'unknown language' => [['problem' => 'hello', 'language' => 'nope'], 'Select a language to run the code.'];
    }

    /**
     * A run gets a judge task of its own, outside of any judging, with the limits of the
     * problem but the default run and compare scripts, so also an interactive problem
     * like boolfind just reads the input of the team.
     */
    public function testRun(): void
    {
        $this->client->request('POST', '/team/run', [
            'problem' => 'boolfind', 'language' => 'java', 'code' => "public class Main {\r\n}\r\n", 'input' => "1 2\r\n",
        ]);
        static::assertResponseIsSuccessful();
        $url = json_decode($this->client->getResponse()->getContent(), true)['url'];

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $config = self::getContainer()->get(ConfigurationService::class);
        $testRun = $em->getRepository(TestRun::class)->findOneBy([], ['testrunid' => 'DESC']);
        static::assertSame("/team/run/{$testRun->getTestrunid()}", $url);
        static::assertSame(['Main.java', "public class Main {\n}\n", "1 2\n"],
            [$testRun->getFilename(), $testRun->getSource(), $testRun->getInput()]);
        $judgeTask = $testRun->getJudgeTask();
        static::assertSame([JudgeTaskType::TEST_RUN, null, null],
            [$judgeTask->getType(), $judgeTask->getJobId(), $judgeTask->getSubmission()]);
        $immutableExecId = fn(string $name) => $em->getRepository(Executable::class)
            ->find($config->get($name))->getImmutableExecutable()->getImmutableExecId();
        static::assertSame([$immutableExecId('default_run'), $immutableExecId('default_compare')],
            [$judgeTask->getRunScriptId(), $judgeTask->getCompareScriptId()]);
        $problem = $em->getRepository(Problem::class)->findOneBy(['externalid' => 'boolfind']);
        static::assertEquals($problem->getTimelimit() * $testRun->getLanguage()->getTimeFactor(),
            Utils::jsonDecode($judgeTask->getRunConfig())['time_limit']);
        static::assertFalse(Utils::jsonDecode($judgeTask->getCompareConfig())['combined_run_compare']);

        // Requests clear the entity manager, so load the test run again after each one.
        $reload = function () use ($testRun): TestRun {
            $em = self::getContainer()->get(EntityManagerInterface::class);
            return $em->getRepository(TestRun::class)->find($testRun->getTestrunid());
        };
        $this->client->request('GET', $url);
        static::assertSame(['status' => 'queued'], json_decode($this->client->getResponse()->getContent(), true));

        $judgehost = (new Judgehost())->setHostname('test-judgehost');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($judgehost);
        $reload()->getJudgeTask()->setJudgehost($judgehost);
        $em->flush();
        $this->client->request('GET', $url);
        static::assertSame(['status' => 'running'], json_decode($this->client->getResponse()->getContent(), true));

        $reload()
            ->setCompileSuccess(true)
            ->setRunresult('timelimit')
            ->setRuntime(5.1)
            ->setOutputRun('1')
            ->setOutputError('')
            ->setMetadata("exitcode: 143\nsignal: 14\nmemory-bytes: 1024\n");
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->client->request('GET', $url);
        $result = json_decode($this->client->getResponse()->getContent(), true);
        static::assertSame(['done', 'timelimit', 5.1, '1', '143', '14', '1024'], [$result['status'], $result['result'],
            $result['runtime'], $result['output'], $result['exitcode'], $result['signal'], $result['memory_bytes']]);

        // A next run takes the place of a queued one.
        $this->client->request('POST', '/team/run', ['problem' => 'hello', 'language' => 'cpp', 'code' => 'int main() {}']);
        $next = json_decode($this->client->getResponse()->getContent(), true)['url'];
        $this->client->request('POST', '/team/run', ['problem' => 'hello', 'language' => 'cpp', 'code' => 'int main() {}']);
        $this->client->request('GET', $next);
        static::assertSame(['status' => 'replaced'], json_decode($this->client->getResponse()->getContent(), true));
    }
}
