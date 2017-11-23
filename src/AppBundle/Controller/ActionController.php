<?php
namespace AppBundle\Controller;

use AppBundle\Entity\SubTask;
use AppBundle\Entity\Action;
use AppBundle\Entity\Notice;
use AppBundle\Entity\OperationalPlan;
use AppBundle\Entity\Emergency;
use AppBundle\Entity\CompensationRoute;
use AppBundle\Rules\ActionStatusRule;
use AppBundle\Service\ActionsService;
use AppBundle\Entity\NotificationTemplate;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use AppBundle\Form\Type\ActionCompleteType;
use AppBundle\Form\Type\SubTaskType;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @Route("/action")
 */
class ActionController extends AbstractCRUDDeletableController
{
    protected function getEntityName()
    {
        return "Action";
    }
    
    /**
     * @return NotificeReportService
     */
    public function noticeReport()
    {
        return $this->get("app.notice.report");
    }
    
    /**
     *
     * @param Request $request
     * @Route("/")
     */
    public function listAction(Request $request)
    {
        return $this->render('AppBundle:Error:error.html.twig', ["message" => "Страницы не существует"]);
    }
    
    /**
     * @Route("/view/{id}")
     * @Template()
     */
    public function viewAction(Request $request, $id)
    {
        $path = $request->getPathInfo();
        if ($permission = $this->permissionRole(substr($path, 0, strripos($path, "/")))) {
            return $permission;
        }

        $entity = $this->getRepository()->find($id);

        if (!$entity) {
            throw $this->createNotFoundException($this->getEntityName() . " с номером " . $id . " не найден ");
        }
        
        return [
            'entity' => $entity,
            'entityName' => $this->getEntityName(),
        ];
    }

    /**
     * @Route("/close/{id}", name="app_action_close")
     */
    public function closeAction(Request $request, $id)
    {
        if ($permission = $this->permissionRole('/action/edit')) {
            return $permission;
        }
        if ($data = $request->request->all()) {
            $action = $this->getRepository()->find($id);
            if ($action) {
                $form = $this->createForm($this->getFormAddTypeName(), $action);
                $form->submit($data);
                if ($form->isValid()) {
                    $em = $this->getDoctrine()->getManager();
                    $em->flush();
                }
            }
        }
        $emergency = $action->getEmergency();
        $this->sendNotification(Notice::NOTICE_EMERGENCY_REGULATION_STEP_CANCEL, $emergency);
        $this->getService()->activateNextActions($action);
        $this->handleActionStep($action, Action::STATUS_STOP);
        $this->noticeByStopAction($action);
        
        return $this->redirect($this->generateUrl('app_emergency_view', ["id" => $emergency->getId()]));
    }
    
    /**
     * @Route("/progress/{id}", name="app_action_progress")
     */
    public function progressAction($id)
    {
        if ($permission = $this->permissionRole('/action/edit')) {
            return $permission;
        }
        
        $action = $this->getRepository()->find($id);
        $action->setUser($this->getUser());
        $this->handleActionStep($action, Action::STATUS_PROGRESS);
        $this->get('app.message.list')->deleteByAction($action);

        return $this->redirect($this->generateUrl('app_emergency_view', ["id" => $action->getEmergency()->getId()]));
    }
    
    /**
     * @Route("/approve/{id}", name="app_action_approve")
     */
    public function approveAction($id)
    {
        if ($permission = $this->permissionRole('/action/edit')) {
            return $permission;
        }
        

        $action = $this->getRepository()->find($id);
        $this->handleActionStep($action, Action::STATUS_FINISHED);

        return $this->redirect($this->generateUrl('app_emergency_view', ["id" => $action->getEmergency()->getId()]));
    }
    
    /**
     * @param Request $request
     * @Route("/complete/{id}", name="app_action_complete")
     * @Template("AppBundle:Action:complete.html.twig")
     */
    public function completeAction(Request $request, $id)
    {
        if ($permission = $this->permissionRole('/action/edit')) {
            return $permission;
        }
        
        /* @var $entity Action*/
        $entity = $this->getRepository()->find($id);
        if (!$entity) {
            throw $this->createNotFoundException($this->getEntityName() . " с номером " . $id . " не найдена");
        }
        
        $isValid = true;

        $form = $this->createForm(ActionCompleteType::class, $entity);

        if ($request->getMethod() == 'POST') {
            $form->handleRequest($request);
            
            if ($isValid = $form->isValid()) {
                try {
                    $emergency = $entity->getEmergency();
                    if ($entity->getOpApproval() == true) {
                        $emergency->getOperationalPlan()->setStatus(OperationalPlan::STATUS_APPROVED);
                    }
                    $op = $entity->getEmergency()->getOperationalPlan();
                    if ($entity->getOpApproval() == true) {
                        $op->setStatus(OperationalPlan::STATUS_APPROVED);
                        if ($crs = $op->getCompensationRoutes()) {
                            foreach ($crs as $cr) {
                                $cr->setStatus(CompensationRoute::STATUS_APPROVED);
                            }
                        }
                    }
                    if ($entity->getCRActivation()) {
                        if ($crs = $op->getCompensationRoutes()) {
                            foreach ($crs as $cr) {
                                $cr->setStatus(CompensationRoute::STATUS_ACTIVE);
                            }
                        }
                    }
                    if ($entity->getCRAgreement()) {
                        if ($crs = $op->getCompensationRoutes()) {
                            foreach ($crs as $cr) {
                                $cr->setStatus(CompensationRoute::STATUS_AGREEMENT);
                            }
                        }
                    }
                    if ($entity->getFinishEmergencyCause() == true) {
                        $emergency->setEmergencyCause(true);
                        $this->sendNotification(Notice::NOTICE_EMERGENCY_FINISH_CAUSE, $emergency);
                    }
                    $status = $this->getService()->approvedCompliteAction($this->getUser());
                    $this->getService()->activateNextActions($entity);
                    $this->handleActionStep($entity, $status);
                    $this->noticeByCompleteAction($entity);
                    $this->customNotice($entity);
                    $this->listVehicleNotice($entity);
                    return $this->redirect($this->generateUrl('app_emergency_view', array('id' => $entity->getEmergency()->getId())));
                } catch (\InvalidArgumentException $e) {
                    $this->handlingFormError($form, $e);
                }
            }
        }
        
        return [
            'entity' => $entity,
            'form' => $form->createView(),
            'entityName' => $this->getEntityName(),
            'isValid' => $isValid
        ];
    }
    
    /**
     * @Route("/edit/{id}")
     * @Template()
     */
    public function editAction(Request $request, $id)
    {
        if ($permission = $this->permissionRole('/action/edit')) {
            return $permission;
        }
        
        $entity = $this->getRepository()->find($id);
        $isValid = true;
        
        if (!$entity) {
            throw $this->createNotFoundException($this->getEntityName() . " с номером " . $id . " не найдена");
        }

        $form = $this->createForm(ActionCompleteType::class, $entity);

        if ($request->getMethod() == 'POST') {
            $form->handleRequest($request);
            
            if ($isValid = $form->isValid()) {
                try {
                    $this->getDoctrine()->getManager()->flush();
                    $this->listVehicleNotice($entity);
                    return $this->redirect($this->generateUrl('app_action_view', array('id' => $id)));
                } catch (\InvalidArgumentException $e) {
                    $this->handlingFormError($form, $e);
                }
            }
        }
        
        return [
            'entity' => $entity,
            'form' => $form->createView(),
            'entityName' => $this->getEntityName(),
            'isValid' => $isValid
        ];
    }

    /**
     * @Route("/create/", name="app_action_create")
     */
    public function createAction(Request $request)
    {
        if ($permission = $this->permissionRole('_REGULATION-STEP_ADD')) {
            return $permission;
        }
        $response = ['error' => false];

        if ($request->isMethod('POST')) {
            $entity = new SubTask();
            $form = $this->createForm(SubTaskType::class, $entity);
            $form->submit($request->request->get('sub-task'));

            if ($form->isValid()) {
                $dataAction = $request->request->get('action');
                $this->getService()->createAction($entity, $dataAction);
                $emergency = $this->getDoctrine()->getManager()->getRepository("AppBundle:Emergency")->find($dataAction['emergency']);
                $this->sendNotification(Notice::NOTICE_EMERGENCY_REGULATION_STEP_ADD, $emergency);
            } else {
                $response = ['error' => $this->getErrorMessages($form)];
            }
        }

        return new Response(json_encode($response));
    }
    
    /**
     * @return ActionStatusRule
     */
    private function getActionStatusRule()
    {
        return $this->get('app.rule.action.status');
    }

    /**
     * @return ActionsService
     */
    private function getService()
    {
        return $this->get('app.actions.service');
    }
    
    /**
     * @return ActionsService
     */
    private function getEmergencyService()
    {
        return $this->get('app.emergency.service');
    }
    
    private function closeEmergency($action)
    {
        if ($this->getService()->isFinishedActions($action->getEmergency())) {
            $this->getEmergencyService()->changeStatus($action->getEmergency(), Emergency::CLOSED_STATUS, $this->getUser());
        }
    }
    
    private function noticeByCompleteAction($action)
    {
        $notices = $this->getDoctrine()->getRepository(Notice::class)->getNoticeByCustomEvent($action->getSubTask());
        if ($notices) {
            foreach ($notices as $notice) {
                $this->notification()->sendNotification($notice, $action->getEmergency());
            }
        }
        
        $this->noticeReport()->createNotice($action, $action->getEmergency());
    }
    
    private function customNotice($action)
    {
        if ($notification = $action->getCustomNotification()) {
            $this->notification()->sendCustomNotification($notification);
        }
    }
    
    /**
     * @param Action $action
     */
    private function listVehicleNotice($action)
    {
        if ($action->getSendListVehicle() and $notification = $action->getNotificationListVehicle()) {
            $data = $this->get("app.operational.plan.service")->getListVehicles($action);
            
            $notification->setTypes([NotificationTemplate::TYPE_EMAIL]);
            $notification->setDescription(implode(";", $data));
            $this->notification()->sendCustomNotification($notification);
        }
    }
    
    private function noticeByStopAction($action)
    {
        $user = $this->getUser();
        if ($user and $boss = $user->getBoss()) {
            $name = $user->getFullName();
            $text = 'Задача ' . (string)$action->getEmergency() . ' ' . $action->getTitle() . ' отклонена пользователем ' . $name;
            $this->notification()->notifyByEmail(null, $boss, $text, $name);
        }
    }
    
    private function logUpdateAction($action, $user)
    {
        if ($action->getStatus() != Action::STATUS_NEW) {
            $data = [$action->getTitle(), (string)$action->getEmergency(), $action->getStatusString()];
            $this->container->get('app.log.user.action')->insert("action_update", $user, $data);
        }
    }
    
    /**
     * @param Action $action
     * @param string $status
     */
    private function handleActionStep($action, $status)
    {
        $this->getActionStatusRule()->move($action, $status);
        $this->closeEmergency($action);
                
        $this->getDoctrine()->getManager()->flush();
        $this->logUpdateAction($action, $this->getUser());
    }
    
    /**
     * @param string $event
     * @param Emergency $emergency
     */
    private function sendNotification($event, $emergency)
    {
        if ($event) {
            $notice = $this->getDoctrine()->getManager()->getRepository(Notice::class)->findOneByEvent($event);
            if ($notice) {
                $this->notification()->sendNotification($notice, $emergency);
            }
        }
    }
}
