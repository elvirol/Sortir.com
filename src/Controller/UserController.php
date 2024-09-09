<?php

namespace App\Controller;

use App\Entity\Campus;
use App\Entity\Activity;
use App\Entity\User;
use App\Form\CsvUploadType;
use App\Form\RegistrationFormType;
use App\Form\UserType;
use App\Repository\CampusRepository;
use App\Repository\ActivityRepository;
use App\Repository\RegistrationRepository;
use App\Repository\UserRepository;
use Couchbase\Role;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user',name: 'app_user')]
class UserController extends AbstractController
{

    #[Route('/', name: '_list', methods: ['GET'])]
    #[IsGranted("ROLE_ADMIN")]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    #[Route('/myAccount', name: '_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(): Response
    {
        $user = $this->getUser();

        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/myAccount/edit', name: '_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(Request $request, EntityManagerInterface $entityManager,UserPasswordHasherInterface $userPasswordHasher): Response
    {
        $user = $this->getUser();

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            /** @var UploadedFile $file */
            $file = $form->get('imageFile')->getData();
            if($file){
                if($user->getProfileImage()) {
                    /** @var UploadedFile $file */
                    $filename = $user->getProfileImage();
                    $filePath = $this->getParameter('kernel.project_dir') . '/public/profile/images/' . $filename;
                    unlink($filePath);
                }
                $filename = $file->getClientOriginalName();
                $file->move($this->getParameter('kernel.project_dir') . '/public/profile/images', $filename);
                $user->setProfileImage($filename);
            }

            // encode the plain password
            if($form->get('password')->getData()){
                $user->setPassword(
                    $userPasswordHasher->hashPassword(
                        $user,
                        $form->get('password')->getData()
                    )
                );
            }
            $entityManager->flush();
            return $this->redirectToRoute('app_home');
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/profile/{id}', name: '_profile', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function organizerProfile(User $user): Response
    {

        return $this->render('user/show.html.twig', [
            'user' => $user,


        ]);
    }

//    ==================== DELETE USER ===============

    #[Route('/delete/{id}', name: '_delete', methods: ['GET'])]
    #[IsGranted("ROLE_ADMIN")]
    public function delete(
        Request $request,
        EntityManagerInterface $entityManager,
        int $id,
        UserRepository $userRepository,
        ActivityRepository $activityRepository,
        RegistrationRepository $registrationRepository
    ):  Response
    {
        $user = $userRepository->find($id);
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->get('token'))) {

            # if user have organized one or more activities
            $id = $userRepository->findIdbyPseudo('Mystery')[0]['id'];
            $activityRepository->replaceOrganizerByDummy($id, $user->getId());
            #

            # if user is register to one or more activities
            $registrationRepository->deleteUserByid($user->getId());
            #

            # Delete user profile image if exist
            if($user->getProfileImage()) {
                /** @var UploadedFile $file */
                $filename = $user->getProfileImage();
                $filePath = $this->getParameter('kernel.project_dir') . '/public/profile/images/' . $filename;
                unlink($filePath);
            }

            $entityManager->remove($user);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_user_list');
    }

    #[Route('/disable/{id}', name: '_disable', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted("ROLE_ADMIN")]
    public function disable(Request $request, EntityManagerInterface $entityManager,int $id, UserRepository $userRepository): Response
    {
        $user = $userRepository->find($id);
        if ($this->isCsrfTokenValid('disable'.$user->getId(), $request->get('token'))) {
            if($user->isActive()){
                $user->setActive(false);
                $entityManager->flush();
                return $this->redirectToRoute('app_user_list');
            } else {
                $user->setActive(true);
                $entityManager->flush();
                return $this->redirectToRoute('app_user_list');
            }
        }
        return $this->redirectToRoute('app_user_list');
    }
    #[Route('/import', name: '_import', methods: ['GET', 'POST'])]
    #[IsGranted("ROLE_ADMIN")]
    public function import(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher,CampusRepository $campusRepository, UserRepository $userRepository): Response
    {

        $form = $this->createForm(CsvUploadType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->isCsrfTokenValid('import', $request->query->get('token'))) {
            $csvFile = $form->get('csv_file')->getData();

            if(filesize($csvFile)<71){
                $this->addFlash('error', 'Votre fichier est vide/imcomplet ou ne contient aucun utilisateur !');
                return $this->redirectToRoute('app_user_import');
            }

            // Vérification de l'extension et du type MIME
            $extension = $csvFile->getClientOriginalExtension();
            $allowedMimeTypes = ['text/csv', 'text/plain'];

            if ($extension !== 'csv' || (!in_array($csvFile->getMimeType(), $allowedMimeTypes))) {
                dd($extension, $csvFile->getMimeType());
                $this->addFlash('error', 'Le type ou extension de votre fichier est invalide. Merci d\'utiliser un fichier .CSV');
                return $this->redirectToRoute('app_user_import');
            }


            try{
                $fileName = uniqid().'.csv';
                $destination = $this->getParameter('csv_directory');
                $csvFile->move($destination, $fileName);
                $filePath = $destination.'/'.$fileName;

                if (($handle = fopen($filePath, 'r')) !== false) {

                    $headersComma = fgetcsv($handle, 1000, ",");
                    rewind($handle);
                    $headersSemiColon = fgetcsv($handle, 1000, ";");
                    $expectedHeaders = ['campus','pseudo','role','mot_de_passe','nom','prenom','telephone','email','est_actif'];

                    if(strpos($headersComma[0],"\u{FEFF}") === 0 || strpos($headersSemiColon[0],"\u{FEFF}") === 0) {
                        $headersComma[0]='campus';
                        $headersSemiColon[0]='campus';
                    }

                    if($expectedHeaders === $headersComma) {
                        $delimiter = ",";
                    }elseif ($expectedHeaders === $headersSemiColon){
                        $delimiter = ";";
                    }else{
                        $this->addFlash('error', 'Entête CSV invalide !');
                        fclose($handle);
                        return $this->redirectToRoute('app_user_import');
                    }

                    while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {
                        // $data est un tableau des valeurs pour une ligne

                        if($data[0]) {

                            $forbiddenChar = "'(){}[],.;:=~";
                            $isPseudoIncorrect = strpbrk($data[1], $forbiddenChar);
                            if (!$isPseudoIncorrect) {
                                $isPseudoUsed = $userRepository->findOneBy(['pseudo' => $data[1]]);
                            }
                            $isMailUsed = $userRepository->findOneBy(['email' => $data[7]]);
                            $isCampusValid = $campusRepository->findOneBy(['name' => $data[0]]);
                            $isActiveValid = true;
                            $isRoleValid = true;
                            $roles[0] = strtoupper($data[2]);
                            if ($roles[0] === "") {
                                $roles[0] = 'ROLE_USER';
                            }

                            if ($isPseudoIncorrect) {
                                $this->addFlash('error', 'Utilisateur : ' . $data[5] . ' ' . $data[4] . ' le pseudo : ' . $data[1] . ' n\'est pas autorisé ! L\'utilisateur n\'a pas été ajouté !');
                            }
                            if ($isPseudoUsed) {
                                $this->addFlash('error', 'Utilisateur : ' . $data[5] . ' ' . $data[4] . ' le pseudo : ' . $data[1] . ' est déjà utilisé ! L\'utilisateur n\'a pas été ajouté !');
                            }
                            if ($isMailUsed) {
                                $this->addFlash('error', 'Utilisateur : ' . $data[5] . ' ' . $data[4] . ' l\'e-mail : ' . $data[7] . ' est déjà utilisé ! L\'utilisateur n\'a pas été ajouté !');
                            }
                            if (!$isCampusValid) {
                                $this->addFlash('error', 'Utilisateur : ' . $data[5] . ' ' . $data[4] . ' le campus : ' . $data[7] . ' n\'est pas valide ! L\'utilisateur n\'a pas été ajouté !');
                            }
                            if ($roles[0] !== 'ROLE_USER' && $roles[0] !== 'ROLE_ADMIN') {
                                $isRoleValid = false;
                                $this->addFlash('error', 'Utilisateur : ' . $data[5] . ' ' . $data[4] . ' le rôle : ' . $data[7] . ' n\'est pas valide ! L\'utilisateur n\'a pas été ajouté ! (utilisateur : ROLE_USER ; admin : ROLE_ADMIN)');
                            }
                            if ($data[8] !== '0' && $data[8] !== '1') {
                                $isActiveValid = false;
                                $this->addFlash('error', 'Utilisateur : ' . $data[5] . ' ' . $data[4] . ' est_actif : ' . $data[8] . ' n\'est pas valide !  L\'utilisateur n\'a pas été ajouté ! (0 = inactif, 1 = actif)');
                            }

                            $user = new User();

                            if (!$isPseudoUsed && !$isMailUsed && $isCampusValid && $isActiveValid && $isRoleValid) {
                                $user->setCampus($isCampusValid);
                                $user->setPseudo($data[1]);
                                $user->setRoles($roles);
                                $passwordHash = $passwordHasher->hashPassword($user, $data[3]);
                                $user->setPassword($passwordHash);
                                $user->setLastName($data[4]);
                                $user->setFirstName($data[5]);
                                $user->setPhone($data[6]);
                                $user->setEmail($data[7]);
                                $user->setActive($data[8]);

                                $entityManager->persist($user);
                                $entityManager->flush();

                                $this->addFlash('success', 'Utilisateurs '.$data[5].' '. $data[4] .' ajoutés avec succès !');
                            }
                        }
                    }

                    fclose($handle);

                    return $this->redirectToRoute('app_user_import');
                }
            } catch (FileException){
                $this->addFlash('error', 'Une erreur est apparue lors de l\'upload du fichier');
            }
        }
        return $this->render('user/import.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
