<?php

namespace App\Form;

use App\Entity\Activity;
use App\Entity\Place;
use App\Entity\State;
use App\Entity\User;
use phpDocumentor\Reflection\Type;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActivityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'label' => "Nom",
                'required' => true,
                'label_attr' => ['class' => 'form-label'],
                'attr' => ['class' => 'form-input']
            ])
            ->add('starting_date', null, [
                'widget' => 'single_text',
                'input' => 'datetime',
                'invalid_message' => 'Veuillez saisir une date valide',
                'label' => "Date",
                'required' => true,
                'label_attr' => ['class' => 'form-label'],
                'attr' => ['class' => 'form-input']
            ])
            ->add('duration_hours', null,  [
                'label' => "Durée (en heure)",
                'required' => false,
                'label_attr' => ['class' => 'form-label'],
                'attr' => ['class' => 'form-input']
            ])
            ->add('registration_limit_date', null, [
                'widget' => 'single_text',
                'input' => 'datetime',
                'invalid_message' => 'Veuillez saisir une date valide',
                'label' => "Date limite d'inscription",
                'required' => true,
                'label_attr' => ['class' => 'form-label'],
                'attr' => ['class' => 'form-input']
            ])
            ->add('registration_max_nb', null,  [
                'label' => "Nombre d'inscriptions maximum",
                'required' => true,
                'label_attr' => ['class' => 'form-label'],
                'attr' => ['class' => 'form-input']
            ])
            ->add('description', null,  [
                'label' => "Description",
                'required' => false,
                'label_attr' => ['class' => 'form-label'],
                'attr' => ['class' => 'form-input']
            ])
            ->add('photo_url', null,  [
                'label' => "URL de la photo",
                'required' => false,
                'label_attr' => ['class' => 'form-label'],
                'attr' => ['class' => 'form-input']
            ])
            ->add('place', null, [
                'class' => Place::class,
                'choice_label' => 'name',
                'label' => 'Lieu',
                'required' => true,
                'label_attr' => ['class' => 'form-label'],
                'attr' => ['class' => 'form-input']
            ])
        ;
    }



    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Activity::class,
        ]);
    }
}
