<?php

namespace App\Controller\Admin;

use App\Entity\Drink;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class DrinkCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Drink::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Boisson')
            ->setEntityLabelInPlural('Boissons')
            ->setPageTitle('index', 'Gestion des boissons')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm()->hideOnIndex(),
            TextField::new('name', 'Nom')->setRequired(true),
            // Admin: garder le format par défaut (comme avant)
            MoneyField::new('price', 'Prix')
                ->setCurrency('EUR')
                ->setStoredAsCents(false),
            ChoiceField::new('type', 'Type')->setChoices([
                'Vins' => 'vins',
                'Bières' => 'bieres',
                'Boissons fraîches' => 'fraiches',
                'Boissons chaudes' => 'chaudes',
            ]),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn(Action $a) => $a->setCssClass('action-delete btn btn-soft-danger btn-sm'))
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn(Action $a) => $a->setCssClass('btn btn-soft-success btn-sm'))
            ->update(Crud::PAGE_INDEX, Action::DETAIL, fn(Action $a) => $a->setCssClass('btn btn-soft-info btn-sm'))
            ->setPermission(Action::DELETE, 'ROLE_ADMIN');
    }
}


