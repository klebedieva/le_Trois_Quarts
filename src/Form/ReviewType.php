<?php

namespace App\Form;

use App\Entity\Review;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Votre nom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre nom complet'
                ],
                'required' => true
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email (optionnel)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'votre@email.com'
                ],
                'required' => false
            ])
            ->add('rating', ChoiceType::class, [
                'label' => 'Note',
                'choices' => [
                    '1 étoile' => 1,
                    '2 étoiles' => 2,
                    '3 étoiles' => 3,
                    '4 étoiles' => 4,
                    '5 étoiles' => 5,
                ],
                'attr' => [
                    'class' => 'form-select'
                ],
                'required' => true,
                'placeholder' => 'Sélectionnez une note'
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Votre avis',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Partagez votre expérience au Trois Quarts...',
                    'rows' => 4
                ],
                'required' => true
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Envoyer l\'avis',
                'attr' => [
                    'class' => 'btn btn-primary'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Review::class,
        ]);
    }
}
