<?php declare(strict_types=1);

namespace App\Controller\Team;

use App\Controller\BaseController;
use App\Entity\Contest;
use App\Entity\ContestProblem;
use App\Entity\Judging;
use App\Entity\Language;
use App\Entity\Problem;
use App\Entity\Submission;
use App\Entity\SubmissionSource;
use App\Entity\Team;
use App\Entity\Testcase;
use App\Entity\TestRun;
use App\Form\Type\SubmitProblemType;
use App\Service\AuthorizedUserService;
use App\Service\ConfigurationService;
use App\Service\DOMJudgeService;
use App\Service\EventLogService;
use App\Service\SubmissionService;
use App\Utils\Utils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TEAM')]
#[IsGranted(
    new Expression('user.getTeam() !== null'),
    message: 'You do not have a team associated with your account.'
)]
#[Route(path: '/team')]
class SubmissionController extends BaseController
{
    final public const NEVER_SHOW_COMPILE_OUTPUT = 0;
    final public const ONLY_SHOW_COMPILE_OUTPUT_ON_ERROR = 1;
    final public const ALWAYS_SHOW_COMPILE_OUTPUT = 2;

    public function __construct(
        protected readonly AuthorizedUserService $authService,
        EntityManagerInterface $em,
        protected readonly SubmissionService $submissionService,
        protected readonly EventLogService $eventLogService,
        DOMJudgeService $dj,
        protected readonly ConfigurationService $config,
        protected readonly FormFactoryInterface $formFactory,
        KernelInterface $kernel,
    ) {
        parent::__construct($em, $eventLogService, $dj, $kernel);
    }

    #[Route(path: '/submit/{problem}', name: 'team_submit')]
    public function createAction(Request $request, ?Problem $problem = null): Response
    {
        $user    = $this->authService->getUser();
        $team    = $user->getTeam();
        $contest = $this->dj->getCurrentContest($user->getTeam()->getTeamid());
        $data = ['languages' => []];
        if ($problem !== null) {
            $data['problem'] = $problem;
            $data['languages'] = $problem->getLanguages()->toArray();
        }

        $submitMethods = $this->config->get('submit_methods');

        $uploadForm = null;
        $pasteForm = null;

        if (in_array('upload', $submitMethods)) {
            $uploadForm = $this->formFactory
                ->createNamedBuilder('submit_problem', SubmitProblemType::class, $data, ['submission_mode' => 'upload'])
                ->setAction($this->generateUrl('team_submit'))
                ->getForm();
        }

        // Only create the paste form for the full page (not the modal)
        if (in_array('paste', $submitMethods) && !$request->isXmlHttpRequest()) {
            // The editor can switch problems, so offer all languages; submitting checks them per problem.
            $pasteForm = $this->formFactory
                ->createNamedBuilder('submit_problem_paste', SubmitProblemType::class, ['languages' => []] + $data, ['submission_mode' => 'paste'])
                ->setAction($this->generateUrl('team_submit'))
                ->getForm();
        }

        // Handle upload form submission
        if ($uploadForm !== null) {
            $uploadForm->handleRequest($request);
            if ($uploadForm->isSubmitted() && $uploadForm->isValid()) {
                return $this->handleSubmission($uploadForm, $team, $contest, 'upload');
            }
        }

        // Handle paste form submission
        if ($pasteForm !== null) {
            $pasteForm->handleRequest($request);
            if ($pasteForm->isSubmitted() && $pasteForm->isValid()) {
                return $this->handleSubmission($pasteForm, $team, $contest, 'paste');
            }
        }

        $templateData = [
            'problem' => $problem,
            'upload_form' => $uploadForm?->createView(),
            'paste_form' => $pasteForm?->createView(),
            'submit_methods' => $submitMethods,
            'allow_test_runs' => $this->config->get('allow_test_runs'),
        ];
        $templateData['validFilenameRegex'] = SubmissionService::FILENAME_REGEX;
        if ($contest && $team) {
            $templateData['rateLimitStatus'] = $this->submissionService->getRateLimitStatus($team, $contest);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('team/submit_modal.html.twig', $templateData);
        } else {
            return $this->render('team/submit.html.twig', $templateData);
        }
    }

    private function handleSubmission(FormInterface $form, Team $team, ?Contest $contest, string $mode): Response
    {
        if ($contest === null) {
            $this->addFlash('danger', 'No active contest');
            return $this->redirectToRoute('team_index');
        }
        if (!$this->authService->checkRole('jury') && !$contest->getFreezeData()->started()) {
            $this->addFlash('danger', 'Contest has not yet started');
            return $this->redirectToRoute('team_index');
        }

        /** @var Problem $problem */
        $problem = $form->get('problem')->getData();
        /** @var Language $language */
        $language = $form->get('language')->getData();
        $entryPoint = $form->get('entry_point')->getData() ?: null;

        $tmpFile = null;
        if ($mode === 'paste') {
            $result = $this->buildPasteFiles($problem, $language, $contest, $form->get('code_content')->getData());
            if ($result === null) {
                return $this->redirectToRoute('team_index');
            }
            [$files, $tmpFile] = $result;
        } else {
            /** @var UploadedFile[]|UploadedFile $files */
            $files = $form->get('code')->getData();
            if (!is_array($files)) {
                $files = [$files];
            }
        }

        try {
            $message = '';
            $submission = $this->submissionService->submitSolution(
                $team, $this->authService->getUser(), $problem->getProbid(), $contest, $language, $files, SubmissionSource::TEAM_PAGE, null,
                null, $entryPoint, null, null, $message
            );

            if ($submission) {
                $this->addFlash('success', 'Submission done! Watch for the verdict in the list below.');
            } else {
                $this->addFlash('danger', $message);
            }
        } finally {
            if ($tmpFile !== null && file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }

        return $this->redirectToRoute('team_index');
    }

    /**
     * Build an UploadedFile from pasted code content.
     *
     * @return array{UploadedFile[], string}|null [files, tmpFilePath], or null if the code content is empty
     */
    private function buildPasteFiles(Problem $problem, Language $language, Contest $contest, ?string $codeContent): ?array
    {
        if ($codeContent === null || trim($codeContent) === '') {
            $this->addFlash('danger', 'No code provided.');
            return null;
        }
        // Browsers send form fields with CRLF line endings, but the editor uses LF;
        // a CR would for example break shell scripts.
        $codeContent = str_replace("\r\n", "\n", $codeContent);

        // Determine filename — find the contest problem for the current contest
        $contestProblem = $problem->getContestProblems()->filter(
            fn($cp) => $cp->getContest() === $contest
        )->first();
        $filename = $this->pasteFilename($language, $contestProblem ?: null, $codeContent);

        // Write to temp file and create UploadedFile
        $tmpFile = tempnam(sys_get_temp_dir(), 'dj_paste_');
        file_put_contents($tmpFile, $codeContent);
        $uploadedFile = new UploadedFile($tmpFile, $filename, null, null, true);

        return [[$uploadedFile], $tmpFile];
    }

    /**
     * javac requires a public top-level class to be in a file with the same name.
     * Other languages use the problem name; the entry point is derived from that.
     */
    private function pasteFilename(Language $language, ?ContestProblem $contestProblem, string $code): string
    {
        $extension = $language->getExtensions()[0] ?? 'txt';
        if ($extension === 'java' && preg_match('/^(?:@\w+(?:\([^)]*\))?\s+)*(?:(?:abstract|final|sealed|non-sealed|strictfp)\s+)*public\s+(?:(?:abstract|final|sealed|non-sealed|strictfp)\s+)*(?:class|interface|enum|record)\s+(\w+)/m', $code, $matches)) {
            $filename = $matches[1] . '.java';
        } elseif ($contestProblem !== null) {
            $filename = $contestProblem->getShortname() . '.' . $extension;
        } else {
            $filename = 'submission.' . $extension;
        }
        return SubmissionService::sanitizeFilename($filename);
    }

    /**
     * Run code from the code editor with input of the team on a judgehost, with the limits of a problem.
     */
    #[Route(path: '/run', name: 'team_run', methods: ['POST'])]
    public function runAction(Request $request): JsonResponse
    {
        $team    = $this->authService->getUser()->getTeam();
        $contest = $this->dj->getCurrentContest($team->getTeamid());
        if (!$this->config->get('allow_test_runs') || !$contest || !$contest->getFreezeData()->started()) {
            throw new NotFoundHttpException('Running code is not available.');
        }

        $contestProblem = $this->em->getRepository(ContestProblem::class)
            ->findByProblemAndContest($contest, (string)$request->request->get('problem'));
        if (!$contestProblem || !$contestProblem->getAllowSubmit()) {
            return new JsonResponse(['error' => 'Select a problem to run the code with its limits.'], 400);
        }
        $language = $this->em->getRepository(Language::class)->findOneBy(['externalid' => $request->request->get('language')]);
        if (!$language || !in_array($language, $this->dj->getAllowedLanguagesForContest($contest), true)) {
            return new JsonResponse(['error' => 'Select a language to run the code.'], 400);
        }
        // Like for submissions, use the line endings of the editor.
        $code  = str_replace("\r\n", "\n", (string)$request->request->get('code'));
        $input = str_replace("\r\n", "\n", (string)$request->request->get('input'));
        $limit = (int)$this->config->get('sourcesize_limit');
        if (strlen($code) > $limit * 1024 || strlen($input) > $limit * 1024) {
            return new JsonResponse(['error' => sprintf('The code and the input can be at most %d kB each.', $limit)], 400);
        }

        $testRun = (new TestRun())
            ->setTeam($team)
            ->setLanguage($language)
            ->setFilename($this->pasteFilename($language, $contestProblem, $code))
            ->setSource($code)
            ->setInput($input);
        $this->dj->queueTestRun($testRun, $contestProblem);
        return new JsonResponse(['url' => $this->generateUrl('team_run_result', ['testRunId' => $testRun->getTestrunid()])]);
    }

    /**
     * The status of a test run and, once done, its result.
     */
    #[Route(path: '/run/{testRunId<\d+>}', name: 'team_run_result')]
    public function runResultAction(int $testRunId): JsonResponse
    {
        $testRun = $this->em->getRepository(TestRun::class)->find($testRunId);
        if (!$testRun || $testRun->getTeam() !== $this->authService->getUser()->getTeam()) {
            throw new NotFoundHttpException('Test run not found.');
        }

        $judgeTask = $testRun->getJudgeTask();
        if (!$judgeTask?->getValid()) {
            // A later run of the team took its place in the queue.
            return new JsonResponse(['status' => 'replaced']);
        }
        if ($judgeTask->getJudgehost() === null) {
            return new JsonResponse(['status' => 'queued']);
        }
        if ($testRun->getCompileSuccess() === null) {
            return new JsonResponse(['status' => 'running']);
        }

        $runConfig = Utils::jsonDecode($judgeTask->getRunConfig());
        $metadata = Utils::parseMetadata($testRun->getMetadata() ?? '');
        return new JsonResponse([
            'status' => 'done',
            'compile_success' => $testRun->getCompileSuccess(),
            'compile_output' => $testRun->getOutputCompile(),
            'result' => $testRun->getRunresult(),
            'runtime' => $testRun->getRuntime(),
            'output' => $testRun->getOutputRun(),
            'error' => $testRun->getOutputError(),
            'exitcode' => $metadata['exitcode'] ?? null,
            'signal' => $metadata['signal'] ?? null,
            'memory_bytes' => $metadata['memory-bytes'] ?? null,
            'time_limit' => $runConfig['time_limit'],
            'memory_limit' => $runConfig['memory_limit'],
        ]);
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route(path: '/submission/{submitId<\d+>}', name: 'team_submission')]
    public function viewAction(Request $request, int $submitId): Response
    {
        $verificationRequired = (bool)$this->config->get('verification_required');
        $showCompile          = $this->config->get('show_compile');
        $showSampleOutput     = $this->config->get('show_sample_output');
        $allowDownload        = (bool)$this->config->get('allow_team_submission_download');
        $showTooLateResult    = $this->config->get('show_too_late_result');
        $user                 = $this->authService->getUser();
        $team                 = $user->getTeam();
        $contest              = $this->dj->getCurrentContest($team->getTeamid());
        if (!$contest) {
            throw new NotFoundHttpException('No active contest');
        }
        /** @var Judging|null $judging */
        $judging = $this->em->createQueryBuilder()
            ->from(Judging::class, 'j')
            ->join('j.submission', 's')
            ->join('s.contest_problem', 'cp')
            ->join('cp.problem', 'p')
            ->join('s.language', 'l')
            ->select('j', 's', 'cp', 'p', 'l')
            ->andWhere('j.submission = :submitId')
            ->andWhere('j.valid = 1')
            ->andWhere('s.team = :team')
            ->setParameter('submitId', $submitId)
            ->setParameter('team', $team)
            ->getQuery()
            ->getOneOrNullResult();

        // Update seen status when viewing submission.
        if ($judging && $judging->getSubmission()->getSubmittime() < $contest->getEndtime() &&
            (!$verificationRequired || $judging->getVerified())) {
            $judging->setSeen(true);
            $this->em->flush();
        }

        $runs = [];
        if ($showSampleOutput && $judging?->getResult() !== 'compiler-error') {
            $outputDisplayLimit    = (int)$this->config->get('output_display_limit');
            $outputTruncateMessage = sprintf("\n[output display truncated after %d B]\n", $outputDisplayLimit);

            $queryBuilder = $this->em->createQueryBuilder()
                ->from(Testcase::class, 't')
                ->join('t.content', 'tc')
                ->leftJoin('t.judging_runs', 'jr', Join::WITH, 'jr.judging = :judging')
                ->leftJoin('jr.output', 'jro')
                ->select('t', 'jr', 'tc')
                ->andWhere('t.problem = :problem')
                ->andWhere('t.sample = 1')
                ->setParameter('judging', $judging)
                ->setParameter('problem', $judging->getSubmission()->getProblem())
                ->orderBy('t.ranknumber');

            if ($outputDisplayLimit < 0) {
                $queryBuilder
                    ->addSelect('tc.output AS output_reference')
                    ->addSelect('jro.output_run AS output_run')
                    ->addSelect('jro.output_diff AS output_diff')
                    ->addSelect('jro.output_error AS output_error')
                    ->addSelect('jro.output_system AS output_system')
                    ->addSelect('jro.team_message AS team_message');
            } else {
                $queryBuilder
                    ->addSelect('TRUNCATE(tc.output, :outputDisplayLimit, :outputTruncateMessage) AS output_reference')
                    ->addSelect('TRUNCATE(jro.output_run, :outputDisplayLimit, :outputTruncateMessage) AS output_run')
                    ->addSelect('TRUNCATE(jro.output_diff, :outputDisplayLimit, :outputTruncateMessage) AS output_diff')
                    ->addSelect('TRUNCATE(jro.output_error, :outputDisplayLimit, :outputTruncateMessage) AS output_error')
                    ->addSelect('TRUNCATE(jro.output_system, :outputDisplayLimit, :outputTruncateMessage) AS output_system')
                    ->addSelect('TRUNCATE(jro.team_message, :outputDisplayLimit, :outputTruncateMessage) AS team_message')
                    ->setParameter('outputDisplayLimit', $outputDisplayLimit)
                    ->setParameter('outputTruncateMessage', $outputTruncateMessage);
            }

            $runs = $queryBuilder
                ->getQuery()
                ->getResult();
        }

        $actuallyShowCompile = $showCompile == self::ALWAYS_SHOW_COMPILE_OUTPUT
            || ($showCompile == self::ONLY_SHOW_COMPILE_OUTPUT_ON_ERROR && $judging?->getResult() === 'compiler-error');

        $data = [
            'judging' => $judging,
            'verificationRequired' => $verificationRequired,
            'showCompile' => $actuallyShowCompile,
            'allowDownload' => $allowDownload,
            'showSampleOutput' => $showSampleOutput,
            'runs' => $runs,
            'showTooLateResult' => $showTooLateResult,
            'thumbnailSize' => $this->config->get('thumbnail_size'),
        ];
        if ($actuallyShowCompile) {
            $data['size'] = 'xl';
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('team/submission_modal.html.twig', $data);
        } else {
            return $this->render('team/submission.html.twig', $data);
        }
    }

    /**
     * @throws NonUniqueResultException
     */
    #[Route(path: '/submission/{submitId<\d+>}/download', name: 'team_submission_download')]
    public function downloadAction(int $submitId): Response
    {
        $allowDownload = (bool)$this->config->get('allow_team_submission_download');
        if (!$allowDownload) {
            throw new NotFoundHttpException('Submission download not allowed');
        }

        $user = $this->authService->getUser();
        $team = $user->getTeam();
        /** @var Submission|null $submission */
        $submission = $this->em->createQueryBuilder()
            ->from(Submission::class, 's')
            ->join('s.files', 'f')
            ->select('s, f')
            ->andWhere('s.submitid = :submitId')
            ->andWhere('s.team = :team')
            ->setParameter('submitId', $submitId)
            ->setParameter('team', $team)
            ->getQuery()
            ->getOneOrNullResult();

        if ($submission === null) {
            throw new NotFoundHttpException(sprintf('Submission with ID \'%s\' not found',
                $submitId));
        }

        if ($this->submissionService->getSubmissionFileCount($submission) === 1) {
            return $this->submissionService->getSubmissionFileResponse($submission);
        }

        return $this->submissionService->getSubmissionZipResponse($submission);
    }
}
