<?php

namespace App\Controller;

use App\Entity\Place;
use App\Form\PlaceType;
use App\Repository\PlaceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/place')]
#[IsGranted('ROLE_USER')]
class PlaceController extends AbstractController
{
    private string $URL = 'https://api-adresse.data.gouv.fr/search/?q=';

    #[Route('/', name: 'app_place_index', methods: ['GET','POST'])]
    public function index(Request $request,PlaceRepository $placeRepository): Response
    {
        $criteria = $request->getPayload()->all(); //filled only in case of POST
        $word = array_key_exists('word',$criteria) ? $criteria['word']:null;
        if($word){
            return $this->render('place/index.html.twig', [
                'places' => $placeRepository->findLike($word),
                'word' => $word
            ]);
        }

        return $this->render('place/index.html.twig', [
            'places' => $placeRepository->findAll(),
            'word' => $word
        ]);
    }

    #[Route('/new', name: 'app_place_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, HttpClientInterface $httpClient): Response
    {
        $place = new Place();
        $form = $this->createForm(PlaceType::class, $place);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try{
                $targetURL = $this->URL;
                foreach (explode(' ',$place->getStreet()) as $word){
                    $targetURL .= $word.'+';
                }
                $targetURL .= $place->getCity()->getPostcode().'+'.$place->getCity()->getName();
                $targetURL .= '&limit=5';
                $response = $httpClient->request('GET', $targetURL);
                $json = json_decode($response->getContent(),true);
                $foundPlaces = $json['features'];
                if(count($foundPlaces)==0){
                    $form->addError(new FormError('La rue renseignée est introuvable !'));
                    return $this->render('place/new.html.twig', [
                        'place' => $place,
                        'form' => $form,
                    ]);
                }
                if(count($foundPlaces)>1){
                    $form->addError(new FormError('Le lieu renseigné n\'est pas assez précis !'));
                    return $this->render('place/new.html.twig', [
                        'place' => $place,
                        'form' => $form,
                    ]);
                }

                $foundCoordinates = $foundPlaces[0]['geometry']['coordinates'];
                $place->setLongitude($foundCoordinates[0]);
                $place->setLatitude($foundCoordinates[1]);

            } catch (Exception $e) {
                $this->addFlash('warning', 'Un problème technique est survenu pour récupérer les coordonnées du lieu !');
            }
            $entityManager->persist($place);
            $entityManager->flush();
            return $this->redirectToRoute('app_place_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('place/new.html.twig', [
            'place' => $place,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_place_show', methods: ['GET'])]
    public function show(Place $place): Response
    {
        return $this->render('place/show.html.twig', [
            'place' => $place,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_place_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Place $place, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PlaceType::class, $place);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_place_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('place/edit.html.twig', [
            'place' => $place,
            'form' => $form,
        ]);
    }

    /*
    #[Route('/{id}', name: 'app_place_delete', methods: ['POST'])]
    public function delete(Request $request, Place $place, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$place->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($place);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_place_index', [], Response::HTTP_SEE_OTHER);
    }
    */
}
