<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Registration;
use App\Form\ActivityType;
use App\Form\CancelActivityType;
use App\Repository\ActivityRepository;
use App\Repository\RegistrationRepository;
use App\Repository\CampusRepository;
use App\Repository\StateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/activity', name: 'app_activity')]
#[IsGranted('ROLE_USER')]
class ActivityController extends AbstractController
{
    #[Route('/{withArchives}', name: '_list',requirements: ['withArchives' => 'true'])]
    public function index(ActivityRepository $activityRepository, CampusRepository $campusRepository, Request $request, bool $withArchives = false): Response
    {
        $criteria = $request->getPayload()->all(); //filled only in case of POST
        $criteria['withArchives'] = $withArchives;
        $criteria['campus'] = array_key_exists('campus',$criteria) ? $campusRepository->find($criteria['campus'])  : null; //$criteria['campus']>0?$criteria['campus']:null
        $criteria['word'] = array_key_exists('word',$criteria) ? strlen($criteria['word'])>0?$criteria['word']:null : null;
        $criteria['startingBefore'] = array_key_exists('startingBefore',$criteria) ? $criteria['startingBefore']  : null;
        $criteria['startingAfter'] = array_key_exists('startingAfter',$criteria) ? $criteria['startingAfter']  : null;
        $criteria['organizer'] = array_key_exists('organizer',$criteria) ? $criteria['organizer'] : null;
        $criteria['registered'] = array_key_exists('registered',$criteria) ? $criteria['registered'] : null;
        $criteria['forthcoming'] = array_key_exists('forthcoming',$criteria) ? $criteria['forthcoming'] : null;
        $criteria['ongoing'] = array_key_exists('ongoing',$criteria) ? $criteria['ongoing'] : null;
        $criteria['done'] = array_key_exists('done',$criteria) ? $criteria['done'] : null;

        $activitiesPreFilter = $activityRepository->findByCriteria($criteria);

        $criteria['startingAfter'] = $criteria['startingAfter'] ? new \DateTime($criteria['startingAfter']):null;
        $criteria['startingBefore']= $criteria['startingBefore'] ? new \DateTime($criteria['startingBefore']):null;
        $attributes = ['campusList' => $campusRepository->findAll(), 'criteria' => $criteria];

        //if none filter leading to sublist
        if(!$criteria['organizer'] and !$criteria['registered'] and !$criteria['forthcoming'] and !$criteria['ongoing'] and !$criteria['done']) {
            return $this->render('activity/list.html.twig', array_merge($attributes,[
                'activities' => $activitiesPreFilter,
            ]));
        }

        //else - if at least one filter then sublist to merge at the end
        $finalList = [];
        if($criteria['organizer']){
            $finalList = array_merge($finalList,array_filter($activitiesPreFilter, fn($a) => $a->getOrganizer()->getId()==$this->getUser()->getId()));
        }
        if($criteria['registered']){
            $tempList = array_filter($activitiesPreFilter, function($a) {
                if(count($a->getRegistrations())>0){
                    foreach($a->getRegistrations() as $registration){
                        if($registration->getUser()->getId()==$this->getUser()->getId()){
                            return true;
                        }
                    }
                }
                return false;
            });
            $finalList = array_merge($finalList,$tempList);
        }
        if($criteria['forthcoming']){
            $finalList = array_merge($finalList,array_filter($activitiesPreFilter, fn($a) => in_array($a->getState()->getName(),['draft','open','full','pending'])));
        }
        if($criteria['ongoing']){
            $finalList = array_merge($finalList,array_filter($activitiesPreFilter, fn($a) => $a->getState()->getName()=='ongoing'));
        }
        if($criteria['done']){
            $finalList = array_merge($finalList,array_filter($activitiesPreFilter, fn($a) => $a->getState()->getName()=='done'));
        }
        return $this->render('activity/list.html.twig', array_merge($attributes,[
            'activities' => array_map(fn($a)=>unserialize($a),array_unique(array_map(fn($a)=>serialize($a),$finalList))),
        ]));

    }

    #[Route('/create/{autoPublish}', name: '_create', requirements: ['autoPublish' => 'true'], methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, StateRepository $stateRepository, bool $autoPublish = false): Response
    {
        $activity = new Activity();
        $activity->setOrganizer($this->getUser());
        $form = $this->createForm(ActivityType::class, $activity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($activity);
            $entityManager->flush();
            $this->addFlash('success', 'La sortie a été créée avec succès !');
            if(!$autoPublish){
                return $this->redirectToRoute('app_activity_detail', ['id' => $activity->getId()]);
            }
            return $this->publish($activity,$entityManager, $stateRepository);
        }

        return $this->render('activity/create.html.twig', [
            'activity' => $activity,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: '_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Activity $activity): Response
    {
        return $this->render('activity/detail.html.twig', [
            'activity' => $activity,
        ]);
    }

    #[Route('/update/{id}', name: '_update', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Activity $activity, EntityManagerInterface $entityManager): Response
    {
        //controle modif faite par organisateur -> in_array('ROLE_ADMIN',$this->getUser()->getRoles())
        $user = $this->getUser();
        if(!in_array("ROLE_ADMIN", $user->getRoles()) && $user->getId() != $activity->getOrganizer()->getId()){
            throw $this->createAccessDeniedException('Seul l\'organisateur ou un admin peuvent annuler une sortie !');
        }

        $form = $this->createForm(ActivityType::class, $activity);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'La sortie a été mise à jour avec succès !');

            return $this->redirectToRoute('app_activity_list');
        }

        return $this->render('activity/update.html.twig', [
            'activity' => $activity,
            'form' => $form,
        ]);
    }

    #[Route('/cancel/{id}', name: '_cancel', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function cancel(Request $request, Activity $activity, EntityManagerInterface $entityManager,StateRepository $stateRepository): Response
    {

        //if not organizer or admin
        $user = $this->getUser();
        if(!in_array("ROLE_ADMIN", $user->getRoles()) && $user->getId() != $activity->getOrganizer()->getId()){
            throw $this->createAccessDeniedException('Seul l\'organisateur ou un admin peuvent annuler une sortie !');
        }

        //if wrong status to cancel activity
        if($activity->getState()->getName() != 'draft' & $activity->getState()->getName() != 'open' & $activity->getState()->getName() == 'full'){
            throw $this->createAccessDeniedException('Seule une sortie dans le statut nouveau ou publié ou complet peut être modifiée !');
        }

        $form = $this->createForm(CancelActivityType::class,$activity);
        $form->handleRequest($request);

        if ($form->isSubmitted() ) {
            $state = $stateRepository->findOneBy(['name' => 'cancelled']);
            $activity->setState($state);
            $entityManager->flush();
            $this->addFlash('success', 'L\'activité a bien été supprimée' );
            return $this->redirectToRoute('app_activity_list', []);
        }

        return $this->render('activity/_cancel.html.twig', [
            'activity' => $activity,
            'form' => $form,
        ]);
    }

    #[Route('/signUp/{id}', name: '_signup', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function signUp(Request $request, Activity $activity, EntityManagerInterface $entityManager, RegistrationRepository $registrationRepository, ActivityRepository $ar, StateRepository $sr): Response
    {
        //controle token
        if(!$this->isCsrfTokenValid('signup'.$activity->getId(), $request->query->get('token'))) {
            throw $this->createAccessDeniedException('Dommage ! Token de confiance à la source manquant !');
        }

        //controle statut activité (donc indirectement la date de limite d'inscription)
        if($activity->getState()->getName() != 'open'){
            //throw $this->createAccessDeniedException('Dommage ! L\'activité ' . $activity->getName() . ' n\'est pas ouverte !');
            $this->addFlash('error', message: 'Inscription impossible ! L\'activité ' . $activity->getName() . ' n\'est pas ouverte !');
            return $this->redirectToRoute('app_activity_list', []);
        }

        //controle user pas déjà inscrit
        $user = $this->getUser();
        $existingRegistration = $registrationRepository->findOneBy([
            'activity' => $activity,
            'user' => $user
        ]);
        if($existingRegistration) {
            $this->addFlash('error', message: 'Inscription impossible ! Vous êtes déjà inscrit(e) sur l\'activité '.$activity->getName());
            return $this->redirectToRoute('app_activity_list', []);
        }

        $registration = new Registration();
        $registration->setActivity($activity);
        $registration->setUser($user);

        $entityManager->persist($registration);
        $entityManager->flush();

        $this->addFlash('success', message: 'Vous êtes enregistré(e) sur l\'activité suivante : ' . $activity->getName());

        //controle -> si max participants atteint on change le statut de l'activité
        //$nbParticipants = $ar->countParticipant($activity->getId()); // /!\ pourquoi faire une fonction dédiée ??
        $nbParticipants = $registrationRepository->count(['activity'=>$activity->getId()]);
        //if($nbParticipants['nb'] >= $activity->getRegistrationMaxNb()){
        if($nbParticipants >= $activity->getRegistrationMaxNb()){
            $state = $sr->findOneBy(['name' => 'full']);
            $activity->setState($state);
            $entityManager->flush();
        }
        return $this->redirectToRoute('app_activity_list', []);
    }

    #[Route('/unsubscribe/{id}', name: '_unsubscribe', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function unsubscribe(Request $request, Activity $activity, EntityManagerInterface $entityManager, RegistrationRepository $registrationRepository, ActivityRepository $ar, StateRepository $sr): Response
    {
        if (!$this->isCsrfTokenValid('unsubscribe' . $activity->getId(), $request->query->get('token'))) {
            throw $this->createAccessDeniedException('Dommage ! Token de confiance à la source manquant !');
        }

        $user = $this->getUser();
        $existingRegistration = $registrationRepository->findOneBy([
            'activity' => $activity,
            'user' => $user
            ]);

        if(!$existingRegistration){
            $this->addFlash('error', message: 'Désinscription impossible ! Vous n\'êtes pas inscrit(e) sur l\'activité suivante : '.$activity->getName().'.');
            return $this->redirectToRoute('app_activity_list', []);
        }

        $entityManager->remove($existingRegistration);
        $entityManager->flush();

        //controle -> si nb participants < max participants
        $nbParticipants = $registrationRepository->count(['activity'=>$activity->getId()]);
        if($activity->getState()->getName()=='full' & $nbParticipants < $activity->getRegistrationMaxNb()){
            $state = $sr->findOneBy(['name' => 'open']);
            $activity->setState($state);
            $entityManager->flush();
        }

        $this->addFlash('success', message: 'Vous êtes désinscrit sur l\'activité suivante : ' . $activity->getName() . '.');
        return $this->redirectToRoute('app_activity_list', []);
    }

    #[Route('/publish/{id}', name: '_publish', requirements: ['id' => '\d+'])]
    public function publish(Activity $activity, EntityManagerInterface $em, StateRepository $stateRepository): Response {
        //if not organizer
        if($activity->getOrganizer()->getId()!=$this->getUser()->getId()){
            $this->addFlash('error', message: 'Publication impossible ! Seul l\'oganisateur peut publier une activité !');
            return $this->redirectToRoute('app_activity_list', []);
        }

        //if not draft state
        if($activity->getState()->getName()!='draft'){
            $this->addFlash('error', message: 'Publication impossible ! Seule une activité non publiée peut être publiée !');
            return $this->redirectToRoute('app_activity_list', []);
        }

        //if registration limit date in past
        if($activity->getRegistrationLimitDate() <= new \DateTime()){
            $this->addFlash('error', message: 'Publication impossible ! La date de fin d\'inscription est passée !');
            return $this->redirectToRoute('app_activity_list', []);
        }

        $activity->setState($stateRepository->findOneBy(['name'=>'open']));
        $em->flush();
        $this->addFlash('success', message: 'L\'activité a été publiée avec succès');
        return $this->redirectToRoute('app_activity_list', []);
    }
}
