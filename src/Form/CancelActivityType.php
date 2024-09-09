<?php

namespace App\Form;

use App\Entity\Activity;
use App\Entity\Place;
use App\Entity\State;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CancelActivityType extends AbstractType
{ public function buildForm(FormBuilderInterface $builder, array $options): void
{
    $builder


        ->add('cancelReason', null,[
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
