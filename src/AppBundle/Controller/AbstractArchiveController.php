<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractArchiveController extends AbstractCRUDController
{
    const PER_PAGE = 17;

    /**
     * @param Request $request
     *
     * @return array
     *
     * @Route("/archive")
     * @Method("GET")
     * @Template()
     */
    public function archiveAction(Request $request)
    {
        if ($permission = $this->permissionRole('_'.$this->getPermissionName(), "LIST")) {
            return $permission;
        }

        $em = $this->getDoctrine()->getManager();
        $filterBuilder = $this->getRepository()->findAllDeleted();
        $filter = $this->createForm($this->getFilterFormTypeName());

        if ($request->query->has($filter->getName())) {
            $filter->submit($request->query->get($filter->getName()));
        }

        $entities = $this->getPaginator()->paginate($filterBuilder, $request->query->getInt('page', 1), $request->query->getInt('limit', static::PER_PAGE));
        $this->get('session')->remove('back');

        return [
            'entities' => $entities,
            'filter' => $filter->createView(),
            'entityName' => $this->getEntityName()
        ];
    }

    /**
     * @return string
     */
    protected function getPermissionName()
    {
        return '';
    }
}
