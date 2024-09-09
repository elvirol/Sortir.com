<?php

namespace App\Form;


use App\Entity\Campus;
use App\Entity\State;
use App\Entity\User;
use App\Repository\CampusRepository;
use App\Repository\StateRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('pseudo',null,[
            'label' => "Pseudo",
                ])

            ->add('last_name',null,[
                'label' => "Nom",
            ])

            ->add('first_name',null,[
                'label' => "Prénom",
            ])

            ->add('phone',null,[
                'label' => "Téléphone",
            ])

            ->add('email',null,[
                'label' => "Email",
            ])
            ->add('campus', EntityType::class, [
                'class' => Campus::class,
                'choice_label' => 'name',
                'query_builder' => function(CampusRepository $campusRepository) {
                    return $campusRepository->createQueryBuilder('s')->orderBy('s.name', 'ASC');
                }
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image du profil',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Image()
                ]
            ])
            ->add('password', RepeatedType::class, [
                'label' => 'Mot de passe',
                'required' => false, //pas obligatoire en édition du profil
                'type' => PasswordType::class,
                'options' => [
                    'attr' => [
                        'autocomplete' => 'new-password',
                    ],
                ],
                'constraints' => [
                    new PasswordStrength([
                    'minScore' => 1,
                    'message' => 'votre mot de passe est trop faible',
                    ])
                ],
                'first_options' => [
                    'label' => 'Mot de passe',
                ],
                'second_options' => [
                    'label' => 'Confirmation',
                ],
                'invalid_message' => 'The password fields must match.',
                // Instead of being set onto the object directly,
                // this is read and encoded in the controller
                'mapped' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
