<?php
/**
 * Created by PhpStorm.
 * User: danielromerocalero
 * Date: 27/5/18
 * Time: 15:18
 */

namespace AppBundle\Controller;

use AppBundle\Entity\Attachment;
use AppBundle\Entity\Poll;
use AppBundle\Entity\PollOption;
use AppBundle\Normalizers\PollNormalizer;
use AppBundle\Services\Facades\CentreFacade;
use AppBundle\Services\Facades\PollFacade;
use AppBundle\Services\Facades\AttachmentFacade;
use AppBundle\Services\Facades\PollOptionFacade;
use AppBundle\Services\Facades\StudentFacade;
use AppBundle\Services\ResponseFactory;
use AppBundle\Services\Utils;
use DateTime;
use DateTimeZone;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/polls")
 */
class PollsController extends Controller
{
    private $studentFacade;
    private $pollFacade;
    private $pollOptionFacade;
    private $centreFacade;
    private $attachmentFacade;
    private $responseFactory;
    private $utils;

    public function __construct(StudentFacade $studentFacade,
                                PollFacade $pollFacade,
                                PollOptionFacade $pollOptionFacade,
                                CentreFacade $centreFacade,
                                AttachmentFacade $attachmentFacade,
                                ResponseFactory $responseFactory,
                                Utils $utils)
    {
        $this->studentFacade = $studentFacade;
        $this->pollFacade = $pollFacade;
        $this->pollOptionFacade = $pollOptionFacade;
        $this->centreFacade = $centreFacade;
        $this->attachmentFacade = $attachmentFacade;
        $this->responseFactory = $responseFactory;
        $this->utils = $utils;
    }

    /**
     * @Route("/{id}", name="listarEncuestasDelCentro")
     * @Method("GET")
     */
    public function getAction(Request $request, $id)
    {
        $centre = $this->centreFacade->find($id);

        return $this->responseFactory->successfulJsonResponse(
            ['polls' =>
                $this->utils->serializeArray(
                    $centre->getMessagesOfType('Poll'), new PollNormalizer()
                )

            ]
        );
    }

    /**
     * @Route("", name="crearEncuesta")
     * @Method("POST")
     */
    public function createAction(Request $request)
    {
        $centre = $this->centreFacade->find($request->get('centre'));
        // TODO: enviar date actual cuando se clica en boton desde front_end
        $sendingDate = new DateTime('2000-01-01', new DateTimeZone('Atlantic/Canary'));
        $limitDate = date_create_from_format('Y-m-d G:i:s', $request->request->get('limitDate') . '23:59:59', new DateTimeZone('UTC'));
        $poll = new Poll(
            $request->request->get('subject'),
            $request->request->get('message'),
            $sendingDate,
            $centre,
            $limitDate,
            $request->request->get('multipleChoice')
        );
        $this->pollFacade->create($poll);

        // TODO: renombrar el fichero que se guarda con id unico
        $tempFile = $_FILES['file']['tmp_name'];
        $fileName = $_FILES['file']['name'];
        if ($tempFile != null) {
            $filePath = $_SERVER['DOCUMENT_ROOT'] .'/hermerest_backend/src/AppBundle/Uploads/Polls/'. $fileName;
            move_uploaded_file($tempFile, $filePath);
            $attachment = new Attachment(
                $fileName,
                $poll
            );
            $this->attachmentFacade->create($attachment);
        }

        $this->sendPoll($request->request->get('studentsIds'), $poll, $this->pollFacade);

        $this->addOptionsToPoll($request->request->get('pollOptions'), $poll, $this->pollOptionFacade);

        return $this->responseFactory->successfulJsonResponse([
            'id' => $poll->getId(),
            'limitDate' => $poll->getLimitDate(),
            'subject' => $poll->getSubject(),
        ]);

    }

    private function sendPoll($studentsIds, $authorization, $authorizationFacade)
    {
        foreach ($studentsIds as $studentId) {
            $student = $this->studentFacade->find($studentId);
            $authorization->addStudent($student);
            $authorizationFacade->edit();
        }
    }

    private function addOptionsToPoll($pollOptions, $poll, $pollOptionFacade)
    {
        foreach ($pollOptions as $pollOptionText) {
            $polls = new PollOption(
                $pollOptionText,
                $poll
            );
            $pollOptionFacade->create($polls);
        }

    }
}