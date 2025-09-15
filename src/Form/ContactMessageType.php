<?php
namespace App\Form;

use App\Entity\ContactMessage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactMessageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    
    {
        $builder
            ->add('firstName', null, ['label' => 'form.firstName'])
            ->add('lastName',  null, ['label' => 'form.lastName'])
            ->add('email',     null, ['label' => 'form.email'])
            ->add('phone',     null, ['label' => 'form.phone', 'required' => false])
            ->add('subject', ChoiceType::class, [
                'label' => 'form.subject',
                'placeholder' => 'form.choose_subject',
                'choices' => [
                    'form.reservation'     => 'reservation',
                    'form.commande'        => 'commande',
                    'form.evenement_prive' => 'evenement_prive',
                    'form.suggestion'      => 'suggestion',
                    'form.reclamation'     => 'reclamation',
                    'form.autre'           => 'autre',
                ],
                'required' => true,
            ])
            ->add('message', TextareaType::class, [
                'label' => 'form.message',
                'attr' => ['rows' => 6],
            ])
            ->add('consent', CheckboxType::class, [
                'label' => 'form.consent',
                'required' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ContactMessage::class]);
    }
}
