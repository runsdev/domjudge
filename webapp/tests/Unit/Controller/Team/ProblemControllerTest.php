<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller\Team;

use PHPUnit\Framework\Attributes\DataProvider;
use App\DataFixtures\Test\DemoAboutToStartContestFixture;
use App\Entity\ContestProblem;
use App\Entity\Problem;
use App\Entity\Testcase;
use App\Tests\Unit\BaseTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Generator;

class ProblemControllerTest extends BaseTestCase
{
    protected array $roles = ['team'];

    /**
     * Test that the problem index page shows the correct information.
     */
    #[DataProvider('withLimitsProvider')]
    public function testIndex(bool $withLimits): void
    {
        $problems = [
            'hello',
            'fltcmp',
            'boolfind',
            'jumble',
            'hangman',
        ];
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $problemStatementsData = $em->createQueryBuilder()
            ->from(Problem::class, 'p')
            ->leftJoin('p.problemStatementContent', 'c')
            ->select('p.externalid, c.content')
            ->getQuery()
            ->getResult();
        $problemStatements = [];
        foreach ($problemStatementsData as $data) {
            $problemStatements[array_search($data['externalid'], $problems)] = $data['content'];
        }

        $this->withChangedConfiguration('show_limits_on_team_page', $withLimits,
            function () use (
                $problemStatements,
                $withLimits,
            ): void {
                $descriptions = [
                    'Hello World',
                    'Float special compare test',
                    'Boolean switch search',
                    'Jumble words',
                    'Hangman',
                ];
                $letters = [
                    'A',
                    'B',
                    'C',
                    'D',
                    'E',
                ];
                $crawler = $this->client->request('GET', '/team/problems');

                // Check that the correct menu item is selected.
                static::assertSelectorTextContains('.nav-item .nav-link.active',
                    'Problemset');

                // Get the card bodies and verify we have exactly five of them.
                $cardBodies = $crawler->filter('.card-body');
                static::assertSame(5, $cardBodies->count());

                for ($i = 0; $i < 5; $i++) {
                    $timelimit = '5 seconds';
                    if ($i === 4) {
                        $timelimit = '1 second';
                    }
                    $card = $cardBodies->eq($i);
                    static::assertSame($letters[$i],
                        $card->filter('.card-title')->text(null, true));
                    static::assertSame($descriptions[$i],
                        $card->filter('h3.card-subtitle')->text(null, true));

                    if ($withLimits) {
                        static::assertSame(
                            'Limits: ' . $timelimit . ' / 2 GB',
                            $card->filter('h4.card-subtitle')->text(null, true)
                        );
                    } else {
                        // The problem type is displayed on the page, so we expect one heading.
                        static::assertSame(1,
                            $card->filter('h4.card-subtitle')->count());
                    }

                    // Download the problem text and make sure it is correct.
                    $problemStatementLink = $card->selectLink('statement');
                    $this->client->click($problemStatementLink->link());

                    static::assertSame($problemStatements[$i], $this->client->getInternalResponse()->getContent());
                }
            });
    }

    public static function withLimitsProvider(): Generator
    {
        yield [false];
        yield [true];
    }

    /**
     * Test that the problems page shows only sample data.
     */
    public function testSamples(): void
    {
        // First, enable two samples for the fltcmp problem.
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var Problem $problem */
        $problem = $em->getRepository(Problem::class)->findOneBy(['externalid' => 'fltcmp']);

        /** @var Testcase[] $samples */
        $samples = [
            1 => $problem->getTestcases()->get(0),
            2 => $problem->getTestcases()->get(2),
        ];
        $samples[1]->setSample(true);
        $samples[2]->setSample(true);
        $em->flush();

        $this->logIn();

        $crawler = $this->client->request('GET', '/team/problems');

        // Get the card bodies.
        $cardBodies = $crawler->filter('.card-body');

        // The first and last card should not have any samples.
        self::assertSame(0,
            $cardBodies->eq(0)->filter('.list-group .list-group-item')->count());
        self::assertSame(0,
            $cardBodies->eq(2)->filter('.list-group .list-group-item')->count());

        // Check the link to download all samples.
        $link = $cardBodies->eq(1)->filter('a')->eq(2);
        self::assertSame('samples', $link->text(null, true));
        self::assertSame(sprintf('/team/%s/samples.zip',
            $problem->getExternalid()),
            $link->attr('href'));

        // Download the sample and make sure the contents are correct.
        $this->client->click($link->link());
        $zipfile = $this->client->getInternalResponse()->getContent();
        $content = $this->unzipString($zipfile);

        for ($i = 1; $i <= 2; $i++) {
            self::assertSame($samples[$i]->getContent()->getInput(), $content["$i.in"]);
            self::assertSame($samples[$i]->getContent()->getOutput(), $content["$i.ans"]);
        }
        // Does not contain more than these 4 files.
        self::assertCount(4, $content);
    }

    /**
     * Use a Markdown statement with math and a script for the fltcmp problem and give it a sample.
     */
    private function useMarkdownStatement(): Testcase
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var Problem $problem */
        $problem = $em->getRepository(Problem::class)->findOneBy(['externalid' => 'fltcmp']);
        $problem
            ->setProblemstatementType('md')
            ->getProblemStatementContent()
            ->setContent("## Input\n\nThe set $$\\{1,2\\}$$ has \$a<b\$.\n\n![A dot](data:image/png;base64,iVBORw0KGgo=)\n\n<script>alert('statement')</script>\n");
        $sample = $problem->getTestcases()->get(0);
        $sample->setSample(true);
        $em->flush();
        return $sample;
    }

    /**
     * Markdown statements are shown in a modal on the problems page, or as a page,
     * with their math and the samples.
     */
    public function testMarkdownStatement(): void
    {
        $sample = $this->useMarkdownStatement();
        $this->logIn();

        $crawler = $this->client->request('GET', '/team/problems');
        $link = $crawler->filter('.card-body')->eq(1)->selectLink('statement');
        self::assertSame('typesetMath', $link->attr('data-ajax-modal-after'));

        $this->client->xmlHttpRequest('GET', $link->attr('href'));
        self::assertSelectorExists('.modal .problem-statement h2');

        $crawler = $this->client->request('GET', $link->attr('href'));
        self::assertResponseIsSuccessful();
        $statement = $crawler->filter('.problem-statement');
        self::assertSame('Input', $statement->filter('h2')->text());
        // Markdown would turn \{ into { and the sanitizer would see a tag in a<b.
        self::assertStringContainsString('The set $$\{1,2\}$$ has $a&lt;b$.', $statement->html());
        self::assertStringNotContainsString('alert', $statement->html());
        self::assertSame('data:image/png;base64,iVBORw0KGgo=', $statement->filter('img')->attr('src'));
        self::assertSame($sample->getContent()->getInput(), $statement->filter('pre')->eq(0)->text(null, false));
        self::assertSame($sample->getContent()->getOutput(), $statement->filter('pre')->eq(1)->text(null, false));
    }

    /**
     * In lockdown teams can only view Markdown statements, in the code editor, and download
     * nothing, and the public pages show no statements.
     */
    public function testLockdown(): void
    {
        $this->useMarkdownStatement();
        $this->logIn();
        $downloads = [
            '/team/problems/hello/statement',
            '/team/fltcmp/samples.zip',
            '/public/problems/fltcmp/statement',
            '/public/fltcmp/samples.zip',
            '/api/contests/demo/problems/hello/statement',
            '/api/contests/demo/samples.zip',
        ];
        foreach ($downloads as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
        }

        $this->withChangedConfiguration('team_lockdown', true, function () use ($downloads): void {
            $crawler = $this->client->request('GET', '/team/problems');
            self::assertSelectorExists('script[src*="team-lockdown.js"]');
            self::assertSame(0, $crawler->selectLink('samples')->count());
            // Teams read the Markdown statement of fltcmp next to the code editor only.
            self::assertSame(0, $crawler->selectLink('statement')->count());

            $this->client->request('GET', '/team/problems/fltcmp/statement');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('script[src*="team-lockdown.js"]');

            foreach ($downloads as $url) {
                $this->client->request('GET', $url);
                self::assertResponseStatusCodeSame(404, $url);
            }
        });
    }

    /**
     * Test that the problems page does not show sample data for interactive problems.
     */
    public function testInteractiveSamples(): void
    {
        // First, enable a sample for the interactive boolfind problem.
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var Problem $problem */
        $problem = $em->getRepository(Problem::class)->findOneBy(['externalid' => 'boolfind']);

        /** @var Testcase $sample */
        $sample = $problem->getTestcases()->get(0);
        $sample->setSample(true);
        $em->flush();

        $this->logIn();

        $crawler = $this->client->request('GET', '/team/problems');

        // Get the card bodies.
        $cardBodies = $crawler->filter('.card-body');

        // The last card should not have any samples.
        self::assertSame(0,
            $cardBodies->eq(2)->filter('.list-group .list-group-item')->count());

        // Check the link to download all samples.
        $link = $cardBodies->eq(2)->filter('a')->eq(1);
        self::assertNotSame('samples', $link->text(null, true));

        // Download the sample and make sure the contents are correct.
        $this->client->request('GET', '/team/' . $problem->getProbid() . '/samples.zip');
        $response = $this->client->getResponse();
        self::assertEquals(404, $response->getStatusCode());
    }

    /**
     * Test that problems teams cannot submit to are not accessible by team and public routes.
     */
    public function testProblemNotAllowedForSubmission(): void
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        /** @var Problem $problem */
        $problem = $em->getRepository(Problem::class)->findOneBy(['externalid' => 'hello']);
        $problem->getTestcases()->get(0)->setSample(true);
        $em->flush();

        $this->logIn();

        $endpoints = [
            '/team/problems/hello/statement',
            '/team/hello/samples.zip',
            '/public/problems/hello/statement',
            '/public/hello/samples.zip',
            '/public/clarifications/by-problem/hello',
        ];
        foreach ($endpoints as $endpoint) {
            $this->client->request('GET', $endpoint);
            $statusCode = $this->client->getResponse()->getStatusCode();
            static::assertSame(200, $statusCode, "Expected status code 200, got {$statusCode} for {$endpoint}");
        }

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->getRepository(Problem::class)->findOneBy(['externalid' => 'hello'])
            ->getContestProblems()->first()->setAllowSubmit(false);
        $em->flush();

        foreach ($endpoints as $endpoint) {
            $this->client->request('GET', $endpoint);
            $statusCode = $this->client->getResponse()->getStatusCode();
            static::assertSame(404, $statusCode, "Expected status code 404, got {$statusCode} for {$endpoint}");
        }
    }

    /**
     * Test that the problems page does not show page, statement, sample data before contest start.
     */
    public function testAccessProblemBeforeContestStarts(): void
    {
        $this->loadFixtures([DemoAboutToStartContestFixture::class]);

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $problems = $em->createQueryBuilder()
            ->select('p.probid')
            ->from(ContestProblem::class, 'cp')
            ->join('cp.problem', 'p')
            ->join('cp.contest', 'c')
            ->where('c.shortname = :shortname')
            ->setParameter('shortname', 'demo')
            ->getQuery()
            ->getResult();
        $probids = array_column($problems, 'probid');

        $this->client->request('GET', '/public/problems');
        static::assertSelectorTextContains('.nav-item .nav-link.disabled', 'Problems');
        static::assertSelectorTextContains('.alert.alert-secondary', 'No problem texts available at this point.');

        foreach ($probids as $id) {
            $endpoints = [
                "/team/problems/{$id}",
                "/team/problems/{$id}/statement",
                "/team/problems/{$id}/samples.zip"
            ];

            foreach ($endpoints as $endpoint) {
                $this->client->request('GET', $endpoint);
                $statusCode = $this->client->getResponse()->getStatusCode();
                static::assertSame(404, $statusCode, "Expected status code 404, got {$statusCode} for {$endpoint}");
            }
        }
    }
}
