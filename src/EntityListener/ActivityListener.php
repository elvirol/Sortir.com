<?php

namespace App\EntityListener;

use App\Entity\Activity;
use App\Repository\StateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;


class ActivityListener
{

    public function __construct(
        private readonly StateRepository $stateRepository,
        private readonly EntityManagerInterface $em
    ) {}

    public function prePersist(Activity $activity) :void
    {
        $state = $this->stateRepository->findOneBy(['name' => "draft"]);

        if(!$activity->getState()) {
            $activity->setState($state);
        }
    }

    public function postLoad(Activity $activity, LifecycleEventArgs $event):void
    {
        if($this->hasToBeArchived($activity)){
            $activity->setArchived(true);
            $this->em->flush(); //pas besoin de persist pour le cas update
        }

        if($this->hasToBePending($activity)){
            $activity->setState($this->stateRepository->findOneBy(['name' => "pending"]));
            $this->em->flush();
        }

        if($this->hasToBeOngoing($activity)){
            $activity->setState($this->stateRepository->findOneBy(['name' => "ongoing"]));
            $this->em->flush();
        }

        if($this->hasToBeDone($activity)){
            $activity->setState($this->stateRepository->findOneBy(['name' => "done"]));
            $this->em->flush();
        }
    }

    private function hasToBeArchived(Activity $activity): bool
    {
        if($activity->isArchived()){
            return false;
        }

        $startingDate = clone $activity->getStartingDate();
        $durationHours = $activity->getDurationHours() ? $activity->getDurationHours():0;
        $endingDate = $startingDate->modify('+'.$durationHours.'hour');

        if($endingDate->modify('+30 day') < new \DateTime()){
            return true;
        }
        return false;
    }

    private function hasToBePending(Activity $activity): bool
    {
        if($activity->getState()->getName()!='open' & $activity->getState()->getName()!='full'){
            return false;
        }

        if($activity->getRegistrationLimitDate() < new \DateTime()){
            return true;
        }
        return false;
    }

    private function hasToBeOngoing(Activity $activity): bool
    {
        if($activity->getState()->getName()!='pending' & $activity->getState()->getName()!='open' & $activity->getState()->getName()!='full'){
            return false;
        }

        if($activity->getStartingDate() < new \DateTime()){
            return true;
        }
        return false;
    }

    private function hasToBeDone(Activity $activity): bool
    {
        if($activity->getState()->getName()!='ongoing'){
            return false;
        }

        $startingDate = clone $activity->getStartingDate();
        $durationHours = $activity->getDurationHours() ? $activity->getDurationHours():0;
        $endingDate = $startingDate->modify('+'.$durationHours.'hour');

        if($endingDate < new \DateTime()){
            return true;
        }
        return false;
    }

}
