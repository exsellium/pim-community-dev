<?php

namespace Pim\Bundle\ImportExportBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Validator\ValidatorInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

use Akeneo\Bundle\BatchBundle\Monolog\Handler\BatchLogHandler;

use Pim\Bundle\EnrichBundle\AbstractController\AbstractDoctrineController;
use Pim\Bundle\BaseConnectorBundle\EventListener\JobExecutionArchivist;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Gaufrette\StreamMode;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Job execution controller
 *
 * @author    Romain Monceau <romain@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class JobExecutionController extends AbstractDoctrineController
{
    /**
     * @var BatchLogHandler
     */
    private $batchLogHandler;

    /**
     * @var JobExecutionArchivist
     */
    private $archivist;

    /**
     * @var string
     */
    private $jobType;

    /**
     * Constructor
     * @param Request                  $request
     * @param EngineInterface          $templating
     * @param RouterInterface          $router
     * @param SecurityContextInterface $securityContext
     * @param FormFactoryInterface     $formFactory
     * @param ValidatorInterface       $validator
     * @param TranslatorInterface      $translator
     * @param RegistryInterface        $doctrine
     * @param BatchLogHandler          $batchLogHandler
     * @param JobExecutionArchivist    $archivist
     * @param string                   $jobType
     * @param SerializerInterface      $serializer
     */
    public function __construct(
        Request $request,
        EngineInterface $templating,
        RouterInterface $router,
        SecurityContextInterface $securityContext,
        FormFactoryInterface $formFactory,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
        RegistryInterface $doctrine,
        BatchLogHandler $batchLogHandler,
        JobExecutionArchivist $archivist,
        $jobType,
        SerializerInterface $serializer
    ) {
        parent::__construct(
            $request,
            $templating,
            $router,
            $securityContext,
            $formFactory,
            $validator,
            $translator,
            $doctrine
        );

        $this->batchLogHandler = $batchLogHandler;
        $this->archivist       = $archivist;
        $this->jobType         = $jobType;
        $this->serializer      = $serializer;
    }
    /**
     * List the reports
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        return $this->render(
            sprintf('PimImportExportBundle:%sExecution:index.html.twig', ucfirst($this->getJobType()))
        );
    }

    /**
     * Show a report
     *
     * @param Request $request
     * @param integer $id
     *
     * @return template
     */
    public function showAction(Request $request, $id)
    {
        $jobExecution = $this->findOr404('AkeneoBatchBundle:JobExecution', $id);
        if ('json' === $request->getRequestFormat()) {
            $archives = [];
            foreach ($this->archivist->getArchives($jobExecution) as $key => $files) {
                $label = $this->translator->transchoice(
                    sprintf('pim_import_export.download_archive.%s', $key),
                    count($files)
                );
                $archives[$key] = [
                    'label' => ucfirst($label),
                    'files' => $files,
                ];
            }

            return new JsonResponse(
                [
                    'jobExecution' => $this->serializer->normalize($jobExecution, 'json'),
                    'hasLog'       => file_exists($jobExecution->getLogFile()),
                    'archives'     => $archives,
                ]
            );
        }

        return $this->render(
            sprintf('PimImportExportBundle:%sExecution:show.html.twig', ucfirst($this->getJobType())),
            array(
                'execution' => $jobExecution,
            )
        );
    }

    /**
     * Download the log file of the job execution
     *
     * @param integer $id
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadLogFileAction($id)
    {
        $jobExecution = $this->findOr404('AkeneoBatchBundle:JobExecution', $id);

        $response = new BinaryFileResponse($jobExecution->getLogFile());
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT);

        return $response;
    }

    /**
     * Download an archived file
     *
     * @param integer $id
     * @param string  $archiver
     * @param string  $key
     *
     * @return StreamedResponse
     */
    public function downloadFilesAction($id, $archiver, $key)
    {
        $jobExecution = $this->findOr404('AkeneoBatchBundle:JobExecution', $id);
        $stream       = $this->archivist->getArchive($jobExecution, $archiver, $key);

        return new StreamedResponse(
            function () use ($stream) {
                $stream->open(new StreamMode('rb'));
                while (!$stream->eof()) {
                    echo $stream->read(8192);
                }
                $stream->close();
            },
            200,
            array('Content-Type' => 'application/octet-stream')
        );

    }

    /**
     * Return the job type of the controller
     *
     * @return string
     */
    protected function getJobType()
    {
        return $this->jobType;
    }
}
