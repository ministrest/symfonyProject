<?php

namespace AppBundle\Entity\Repository;

use AppBundle\Entity\Action;
use AppBundle\Entity\Emergency;

class ActionRepository extends BaseRepository
{
    /**
     * @return Action[]|null
     */
    public function findActive()
    {
        $query = $this->createQueryBuilder('e')
            ->leftJoin('e.emergency', 'em')
            ->where('e.startDate IS NOT NULL and e.endDate IS NULL')
            ->andWhere('em.status in (:approve, :open)')
            ->setParameter('approve', Emergency::APPROVED_STATUS)
            ->setParameter('open', Emergency::NEW_STATUS);

        return $query->getQuery()->execute();
    }

    public function getEmergencyFilterBuilder($emergency)
    {
        $qb = $this->createQueryBuilder('e')
            ->where('e.emergency = :emergency')
            ->join('e.subTask', 'st')
            ->setParameter(':emergency', $emergency)
            ->orderBy('e.startDate', 'ASC');
        ;

        return $qb;
    }
}
