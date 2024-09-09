<?php

namespace App\Form;

use App\Entity\Campus;
use App\Entity\User;
use App\Repository\CampusRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\PasswordStrength;

class RegistrationFormType extends AbstractType
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
                'label' => 'Site',
                'class' => Campus::class,
                'choice_label' => 'name',
                'query_builder' => function(CampusRepository $campusRepository) {
                    return $campusRepository->createQueryBuilder('s')->orderBy('s.name', 'ASC');
                }
            ])
            ->add('roles', ChoiceType::class, [
                'choices'  => [
                    'Admin' => 'ROLE_ADMIN',
                    //'User' => 'ROLE_USER',
                ],
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('imageFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Image()
                ]
            ])
            ->add('plainPassword', RepeatedType::class, [
                // instead of being set onto the object directly,
                // this is read and encoded in the controller
                'mapped' => false,
                'type' => PasswordType::class,
                'options' => [
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'invalid_message' => 'mot de passe incorrect',
                'first_options' => [
                    'label' => 'Mot de passe',
                    'constraints' => [
                        new PasswordStrength([
                            'minScore' => 1,
                            'message' => 'votre mot de passe est trop faible',
                        ])
                   ],

                ],
                'second_options' => [
                    'label' => 'Confirmation',
                ]
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
