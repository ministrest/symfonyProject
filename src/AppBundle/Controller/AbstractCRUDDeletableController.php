<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractCRUDDeletableController extends AbstractArchiveController
{
    /**
     * @Route("/delete/{id}")
     */
    public function deleteAction(Request $request, $id)
    {
        $path = $request->getPathInfo();
        if ($permission = $this->permissionRole(substr($path, 0, strripos($path, "/")))) {
            return $permission;
        }

        $entity = $this->getRepository()->find($id);
        $em = $this->getDoctrine()->getManager();
        $this->beforeDelete($entity);
        $entity->setDeletedAt(new \DateTime());
        $em->flush();

        return $this->redirectToRoute('app_' . strtolower($this->getEntityName()) . '_list');
    }
}
