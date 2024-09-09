<?php

namespace App\Form;

use App\Entity\Campus;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use function Sodium\add;

class CsvUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('csv_file', FileType::class, [
            'label' => 'Fichier CSV',
            'mapped' => false,
            'required' => true,
                'constraints' => [
                    new File([
                        'mimeTypes' => [
                            'text/csv',
                            'text/plain',
                        ],
                        'mimeTypesMessage' => 'Veuillez télécharger un fichier CSV valide.',
                    ])
                ]
            ]);
    }

    /*
     $builder->add('csv_file', FileType::class, [
            'label' => 'Fichier CSV',
            'mapped' => false,
            'required' => true,
            'constraints' => [
                new File([
                    'mimeTypes' => [
                        'text/csv',
                        'text/plain',
                        'application/vnd.ms-excel',
                        'text/comma-separated-values',
                        'application/csv',
                    ],
                    'mimeTypesMessage' => 'Veuillez télécharger un fichier CSV valide.',
                ]),
            ],
        ]);
     */

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
