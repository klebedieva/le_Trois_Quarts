// Dish Detail Page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Get dish ID from URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const dishId = urlParams.get('id');
    
    if (!dishId) {
        // If no dish ID, redirect to menu page
        window.location.href = 'menu.html';
        return;
    }
    
    // Find dish in menu data
    const dish = findDishById(dishId);
    
    if (!dish) {
        // If dish not found, redirect to menu page
        window.location.href = 'menu.html';
        return;
    }
    
    // Load dish data
    loadDishData(dish);
    
    // Initialize quantity controls
    initQuantityControls(dish);
    
    // Load related dishes
    loadRelatedDishes(dish);
    
    // Add event listener for cart updates
    addCartUpdateListener(dish);
});

function findDishById(dishId) {
    // Search in menuItems array
    if (window.menuItems) {
        return window.menuItems.find(item => item.id === dishId);
    }
    
    // Fallback: search in drinks data
    if (window.drinksData) {
        return window.drinksData.find(item => item.id === dishId);
    }
    
    return null;
}

function loadDishData(dish) {
    // Update page title
    document.title = `${dish.name} - Le Trois Quarts | Brasserie Marseille`;
    
    // Update breadcrumb
    const breadcrumbItem = document.querySelector('.breadcrumb-item.active');
    if (breadcrumbItem) {
        breadcrumbItem.textContent = dish.name;
    }
    
    // Update dish title
    const dishTitle = document.getElementById('dishTitle');
    if (dishTitle) {
        dishTitle.textContent = dish.name;
    }
    
    // Update dish description
    const dishDescription = document.getElementById('dishDescription');
    if (dishDescription) {
        dishDescription.textContent = dish.description;
    }
    
    // Update dish price
    const dishPrice = document.getElementById('dishPrice');
    if (dishPrice) {
        dishPrice.textContent = `${dish.price}€`;
    }
    
    // Update dish image
    const dishImage = document.getElementById('dishImage');
    if (dishImage) {
        dishImage.src = dish.image;
        dishImage.alt = dish.name;
    }
    
    // Update badges
    updateDishBadges(dish);
    
    // Update ingredients (generate based on dish data)
    updateDishIngredients(dish);
    
    // Update allergens (generate based on dish data)
    updateDishAllergens(dish);
    
    // Update preparation info
    updatePreparationInfo(dish);
    
    // Update nutrition info
    updateNutritionInfo(dish);
    
    // Update reviews (generate sample reviews)
    updateReviews(dish);
}

function updateDishBadges(dish) {
    const badgesContainer = document.querySelector('.dish-badges');
    if (badgesContainer && dish.badges) {
        badgesContainer.innerHTML = '';
        dish.badges.forEach(badge => {
            const badgeElement = document.createElement('span');
            badgeElement.className = 'badge bg-warning me-2';
            badgeElement.textContent = badge;
            badgesContainer.appendChild(badgeElement);
        });
    }
}

function updateDishIngredients(dish) {
    const ingredientsList = document.getElementById('dishIngredients');
    if (ingredientsList) {
        let ingredients = [];
        
        // If dish has ingredients field, use it
        if (dish.ingredients) {
            ingredients = dish.ingredients.split(', ');
        } else {
            // Otherwise generate ingredients based on name
            ingredients = generateIngredients(dish);
        }
        
        ingredientsList.innerHTML = '';
        ingredients.forEach(ingredient => {
            const li = document.createElement('li');
            li.textContent = ingredient;
            ingredientsList.appendChild(li);
        });
    }
}

function generateIngredients(dish) {
    // Generate ingredients based on dish name and category
    const ingredientsMap = {
        'Asperges Printemps à la Ricotta': [
            'asperges vertes fraîches',
            'ricotta maison',
            'oignons rouges',
            'graines de moutarde',
            'vinaigre de cidre',
            'huile d\'olive extra vierge',
            'sel et poivre',
            'herbes fraîches',
            'citron',
            'sucre de canne'
        ],
        'Œuf Mollet au Safran et Petits Pois': [
            'œufs frais',
            'safran de qualité',
            'petits pois frais',
            'graines de sésame noir',
            'crème fraîche',
            'beurre',
            'huile d\'olive extra vierge',
            'sel et poivre',
            'herbes fraîches',
            'citron'
        ],
        'Seiches Sautées à la Chermoula': [
            'seiches fraîches',
            'jeunes pousses d\'épinards',
            'betteraves',
            'fêta',
            'ail',
            'coriandre',
            'citron',
            'huile d\'olive extra vierge',
            'épices marocaines',
            'sel et poivre'
        ],
        'Carpaccio de bœuf': [
            'fines lamelles de bœuf',
            'roquette fraîche',
            'parmesan affiné',
            'pignons de pin',
            'huile d\'olive extra vierge',
            'citron',
            'sel et poivre',
            'huile de truffe'
        ],
        'Soupe du jour': [
            'légumes de saison',
            'bouillon de légumes',
            'croûtons maison',
            'herbes fraîches',
            'crème fraîche',
            'sel et poivre',
            'huile d\'olive'
        ],

        'Bouillabaisse marseillaise': [
            'poissons de roche frais',
            'tomates provençales',
            'oignons et fenouil',
            'ail et persil',
            'safran de provence',
            'huile d\'olive extra vierge',
            'rouille traditionnelle',
            'croûtons de pain'
        ],
        'Entrecôte grillée': [
            'entrecôte 300g',
            'frites maison',
            'salade verte',
            'beurre maître d\'hôtel',
            'herbes de provence',
            'sel et poivre',
            'huile d\'olive'
        ],
        'Potimarron Rôti aux Saveurs d\'Asie': [
            'potimarron frais',
            'chou-fleur',
            'roquette fraîche',
            'œufs frais',
            'sauce soja',
            'nori (algue séchée)',
            'beurre',
            'crème fraîche',
            'huile d\'olive extra vierge',
            'sel et poivre',
            'herbes fraîches'
        ],

        'Tartelette aux Marrons Suisses': [
            'marrons suisses',
            'pâte sablée',
            'meringue italienne',
            'crème pâtissière',
            'sucre',
            'beurre',
            'œufs frais',
            'vanille'
        ],
        'Tartelette Ricotta au Miel et Fraises': [
            'ricotta fraîche',
            'miel de qualité',
            'fraises fraîches',
            'rhubarbe',
            'pâte sablée',
            'sucre',
            'beurre',
            'œufs frais',
            'vanille'
        ],
        'Crémeux Yuzu aux Fruits Rouges': [
            'yuzu frais',
            'fruits rouges frais',
            'meringues',
            'noisettes',
            'crème fraîche',
            'sucre',
            'œufs frais',
            'vanille'
        ],
        'Gaspacho Tomates et Melon': [
            'tomates fraîches',
            'melon',
            'basilic frais',
            'fêta',
            'huile d\'olive extra vierge',
            'vinaigre',
            'ail',
            'sel et poivre'
        ]
    };
    
    return ingredientsMap[dish.name] || [
        'Ingrédients frais',
        'Épices sélectionnées',
        'Herbes aromatiques',
        'Huile d\'olive extra vierge',
        'Sel et poivre'
    ];
}

function updateDishAllergens(dish) {
    const allergensContainer = document.querySelector('.allergen-badges');
    if (allergensContainer) {
        let allergens = [];
        
        // If dish has allergens field, use it
        if (dish.allergens && dish.allergens.length > 0) {
            const allergenLabels = {
                'lactose': 'Lactose',
                'gluten': 'Gluten',
                'nuts': 'Fruits à coque',
                'eggs': 'Œufs',
                'fish': 'Poisson',
                'shellfish': 'Crustacés',
                'soy': 'Soja',
                'peanuts': 'Arachides'
            };
            
            allergens = dish.allergens.map(allergen => allergenLabels[allergen] || allergen);
        } else {
            // Otherwise generate allergens based on name
            allergens = generateAllergens(dish);
        }
        
        allergensContainer.innerHTML = '';
        allergens.forEach(allergen => {
            const badge = document.createElement('span');
            badge.className = 'badge bg-info me-2';
            badge.textContent = allergen;
            allergensContainer.appendChild(badge);
        });
    }
}

function generateAllergens(dish) {
    // Generate allergens based on dish
    const allergensMap = {
        'Asperges Printemps à la Ricotta': ['Lactose'],
        'Œuf Mollet au Safran et Petits Pois': ['Lactose', 'Œufs', 'Fruits à coque'],
        'Seiches Sautées à la Chermoula': ['Poisson', 'Lactose'],
        'Carpaccio de bœuf': ['Lactose'],
        'Soupe du jour': ['Gluten'],

        'Bouillabaisse marseillaise': ['Poisson', 'Gluten', 'Œufs'],
        'Entrecôte grillée': ['Lactose'],
        'Potimarron Rôti aux Saveurs d\'Asie': ['Lactose', 'Œufs', 'Soja'],

        'Tartelette aux Marrons Suisses': ['Gluten', 'Lactose', 'Œufs'],
        'Tartelette Ricotta au Miel et Fraises': ['Gluten', 'Lactose', 'Œufs'],
        'Crémeux Yuzu aux Fruits Rouges': ['Lactose', 'Œufs', 'Fruits à coque'],
        'Gaspacho Tomates et Melon': ['Lactose']
    };
    
    return allergensMap[dish.name] || ['Gluten'];
}

function updatePreparationInfo(dish) {
    const preparationTab = document.getElementById('preparation');
    if (preparationTab) {
        const prepInfo = generatePreparationInfo(dish);
        preparationTab.innerHTML = `
            <p>${prepInfo.description}</p>
            <p><strong>Temps de préparation :</strong> ${prepInfo.time}</p>
            <p><strong>Conseil du chef :</strong> ${prepInfo.tip}</p>
        `;
    }
}

function generatePreparationInfo(dish) {
    const prepMap = {
        'Asperges Printemps à la Ricotta': {
            description: 'Nos asperges vertes sont cuites à la vapeur pour préserver leur croquant et leur saveur naturelle. La ricotta est préparée maison avec du lait frais et la crème est assaisonnée avec des herbes fraîches. Les oignons sont marinés dans un vinaigre parfumé et les graines de moutarde sont toastées pour un contraste de textures.',
            time: '20-25 minutes',
            tip: 'Dégustez avec un verre de vin blanc sec pour parfaitement accompagner les saveurs fraîches et végétales des asperges.'
        },
        'Œuf Mollet au Safran et Petits Pois': {
            description: 'Notre œuf mollet est cuit à la perfection avec du safran de qualité pour une couleur dorée et un goût unique. La crème de petits pois est préparée avec des pois frais et de la crème fraîche pour une texture onctueuse. Les tuiles noires au sésame ajoutent un contraste de texture et de saveur.',
            time: '15-20 minutes',
            tip: 'Dégustez avec un verre de vin blanc sec pour parfaitement accompagner les saveurs délicates du safran et des petits pois.'
        },
        'Seiches Sautées à la Chermoula': {
            description: 'Nos seiches sont sautées à la perfection pour préserver leur tendreté et leur saveur naturelle. La chermoula est préparée avec des jeunes pousses d\'épinards fraîches, de l\'ail, de la coriandre et des épices marocaines authentiques. Le coulis de betteraves apporte une touche de douceur et de couleur, tandis que la fêta ajoute une note salée et crémeuse.',
            time: '20-25 minutes',
            tip: 'Accompagnez d\'un verre de vin blanc sec pour parfaitement accompagner les saveurs méditerranéennes et marocaines.'
        },
        'Carpaccio de bœuf': {
            description: 'Notre carpaccio est préparé avec du bœuf de première qualité, finement tranché et assaisonné selon la tradition italienne.',
            time: '10-15 minutes',
            tip: 'Dégustez avec notre huile de truffe maison pour une expérience gastronomique unique.'
        },
        'Bouillabaisse marseillaise': {
            description: 'Notre bouillabaisse est préparée selon la tradition marseillaise. Les poissons de roche sont soigneusement sélectionnés chaque matin au marché aux poissons.',
            time: '25-30 minutes',
            tip: 'Accompagnez votre bouillabaisse de notre rouille maison et de croûtons grillés.'
        },
        'Boulette d\'agneau': {
            description: 'Nos boulettes d\'agneau sont préparées à la main avec de l\'agneau haché frais, parfumées aux herbes de Provence et épices traditionnelles. Les carottes sont rôties au cumin et miel pour un goût unique.',
            time: '25-30 minutes',
            tip: 'Accompagnez de notre yaourt grec à la citronnelle et miel pour une touche fraîche et parfumée.'
        },
        'Galinette poêlée à l\'ajo blanco': {
            description: 'Notre galinette est poêlée à la perfection avec du beurre et des herbes fraîches. L\'ajo blanco est préparé selon la tradition andalouse avec de l\'ail frais et des amandes torréfiées, créant un contraste unique entre chaud et froid.',
            time: '25-30 minutes',
            tip: 'Dégustez avec notre huile parfumée à la ciboulette et le poivre du Sichuan pour une expérience gastronomique exceptionnelle.'
        },
        'Spaghettis à l\'ail noir et parmesan': {
            description: 'Nos spaghettis sont cuits al dente selon la tradition italienne. Le jus de veau est réduit avec de l\'ail noir pour une saveur profonde et complexe, rehaussé par le citron confit et le parmesan affiné.',
            time: '20-25 minutes',
            tip: 'Dégustez avec un verre de vin rouge pour accompagner parfaitement les saveurs du jus de veau.'
        },

        'Loup de mer aux pois chiches': {
            description: 'Notre loup de mer est grillé à la perfection selon les traditions méditerranéennes. La salade de pois chiches est préparée avec des tomates séchées, petits pois et olives de Kalamata pour un goût authentique.',
            time: '25-30 minutes',
            tip: 'Accompagnez d\'un verre de vin blanc sec pour parfaitement accompagner les saveurs méditerranéennes.'
        },

        'Magret de canard au fenouil confit': {
            description: 'Notre magret de canard est préparé selon la tradition française, servi avec du fenouil confit au vin blanc et une crème de betterave parfumée aux herbes fraîches.',
            time: '30-35 minutes',
            tip: 'Accompagnez d\'un verre de vin rouge de Bordeaux pour parfaitement accompagner les saveurs riches du magret de canard.'
        },
        'Velouté de châtaignes aux pleurottes': {
            description: 'Notre velouté de châtaignes est préparé avec des châtaignes fraîches de saison, crémeux et parfumé aux herbes de Provence. Les pleurottes sont sautées à la perfection et la coppa grillée ajoute une touche de saveur unique.',
            time: '25-30 minutes',
            tip: 'Accompagnez d\'un verre de vin blanc sec pour parfaitement accompagner les saveurs terreuses des châtaignes et des pleurottes.'
        },
        'Sashimi de ventrèche de thon fumé': {
            description: 'Notre sashimi de ventrèche de thon est fumé au charbon actif pour une saveur unique et intense. La crème fumée ajoute une touche crémeuse et les herbes fraîches apportent fraîcheur et équilibre.',
            time: '15-20 minutes',
            tip: 'Accompagnez d\'un verre de saké ou de vin blanc sec pour parfaitement accompagner les saveurs japonaises et fumées.'
        },
        'Potimarron Rôti aux Saveurs d\'Asie': {
            description: 'Notre potimarron est rôti au four pour développer ses saveurs naturelles sucrées. La mousseline de chou-fleur est préparée avec de la crème fraîche et du beurre pour une texture onctueuse. Le jaune d\'œuf est confit dans la sauce soja pour un goût umami unique, et le nori ajoute une touche japonaise authentique.',
            time: '30-35 minutes',
            tip: 'Accompagnez d\'un verre de vin blanc sec ou de saké pour parfaitement accompagner les saveurs fusion franco-japonaises.'
        },

        'Tartelette aux Marrons Suisses': {
            description: 'Notre tartelette aux marrons suisses est préparée avec une pâte sablée maison et des marrons suisses de qualité. La crème pâtissière est parfumée à la vanille et la meringue italienne est préparée à la perfection pour un contraste de textures et de saveurs.',
            time: '30-35 minutes',
            tip: 'Dégustez avec un café ou un thé pour parfaitement accompagner les saveurs douces et automnales des marrons.'
        },
        'Tartelette Ricotta au Miel et Fraises': {
            description: 'Notre tartelette ricotta est préparée avec une ricotta fraîche et du miel de qualité. Les fraises fraîches et la compotée de rhubarbe apportent une touche de fraîcheur et d\'acidité qui équilibre parfaitement la douceur du miel et de la ricotta.',
            time: '25-30 minutes',
            tip: 'Dégustez avec un thé vert ou un café léger pour parfaitement accompagner les saveurs printanières et fraîches.'
        },
        'Crémeux Yuzu aux Fruits Rouges': {
            description: 'Notre crémeux yuzu est préparé avec du yuzu frais importé du Japon pour une saveur authentique et unique. Les fruits rouges frais apportent une touche de fraîcheur et d\'acidité, tandis que les meringues et noisettes ajoutent un contraste de textures et de saveurs.',
            time: '20-25 minutes',
            tip: 'Dégustez avec un thé vert japonais ou un café léger pour parfaitement accompagner les saveurs fusion franco-japonaises.'
        },
        'Gaspacho Tomates et Melon': {
            description: 'Notre gaspacho est préparé avec des tomates fraîches et du melon de saison pour une soupe froide rafraîchissante. Le basilic frais apporte une touche aromatique et la fêta ajoute une note salée qui équilibre parfaitement la douceur du melon.',
            time: '15-20 minutes',
            tip: 'Dégustez bien frais avec un verre de vin blanc sec pour parfaitement accompagner les saveurs méditerranéennes.'
        }
    };
    
    return prepMap[dish.name] || {
        description: 'Notre plat est préparé avec soin selon les traditions culinaires françaises, en utilisant des ingrédients frais et de qualité.',
        time: '20-25 minutes',
        tip: 'Dégustez chaud pour une expérience optimale.'
    };
}

function updateNutritionInfo(dish) {
    const nutritionTab = document.getElementById('nutrition');
    if (nutritionTab) {
        const nutritionInfo = generateNutritionInfo(dish);
        nutritionTab.innerHTML = `
            <div class="nutrition-info">
                <div class="row">
                    <div class="col-6">
                        <p><strong>Calories :</strong> ${nutritionInfo.calories} kcal</p>
                        <p><strong>Protéines :</strong> ${nutritionInfo.proteins}g</p>
                        <p><strong>Glucides :</strong> ${nutritionInfo.carbs}g</p>
                    </div>
                    <div class="col-6">
                        <p><strong>Lipides :</strong> ${nutritionInfo.fats}g</p>
                        <p><strong>Fibres :</strong> ${nutritionInfo.fiber}g</p>
                        <p><strong>Sodium :</strong> ${nutritionInfo.sodium}mg</p>
                    </div>
                </div>
            </div>
        `;
    }
}

function generateNutritionInfo(dish) {
    // Generate nutrition info based on dish
    const nutritionMap = {
        'Asperges Printemps à la Ricotta': { calories: 280, proteins: 12, carbs: 15, fats: 16, fiber: 6, sodium: 450 },
        'Œuf Mollet au Safran et Petits Pois': { calories: 240, proteins: 14, carbs: 12, fats: 14, fiber: 4, sodium: 400 },
        'Seiches Sautées à la Chermoula': { calories: 260, proteins: 22, carbs: 8, fats: 14, fiber: 6, sodium: 550 },
        'Carpaccio de bœuf': { calories: 280, proteins: 25, carbs: 8, fats: 16, fiber: 2, sodium: 600 },
        'Bouillabaisse marseillaise': { calories: 420, proteins: 35, carbs: 15, fats: 25, fiber: 3, sodium: 1200 },
        'Entrecôte grillée': { calories: 580, proteins: 45, carbs: 20, fats: 35, fiber: 2, sodium: 900 },
        'Boulette d\'agneau': { calories: 480, proteins: 28, carbs: 25, fats: 22, fiber: 4, sodium: 750 },
        'Galinette poêlée à l\'ajo blanco': { calories: 520, proteins: 35, carbs: 22, fats: 32, fiber: 6, sodium: 850 },
        'Spaghettis à l\'ail noir et parmesan': { calories: 480, proteins: 18, carbs: 65, fats: 15, fiber: 3, sodium: 750 },

        'Loup de mer aux pois chiches': { calories: 380, proteins: 32, carbs: 28, fats: 16, fiber: 8, sodium: 650 },

        'Magret de canard au fenouil confit': { calories: 580, proteins: 35, carbs: 12, fats: 38, fiber: 4, sodium: 800 },
        'Velouté de châtaignes aux pleurottes': { calories: 320, proteins: 12, carbs: 28, fats: 18, fiber: 6, sodium: 650 },
        'Sashimi de ventrèche de thon fumé': { calories: 280, proteins: 32, carbs: 8, fats: 12, fiber: 2, sodium: 850 },
        'Potimarron Rôti aux Saveurs d\'Asie': { calories: 320, proteins: 8, carbs: 25, fats: 18, fiber: 8, sodium: 600 },

        'Tartelette aux Marrons Suisses': { calories: 320, proteins: 6, carbs: 45, fats: 12, fiber: 3, sodium: 200 },
        'Tartelette Ricotta au Miel et Fraises': { calories: 280, proteins: 8, carbs: 35, fats: 14, fiber: 4, sodium: 180 },
        'Crémeux Yuzu aux Fruits Rouges': { calories: 260, proteins: 6, carbs: 30, fats: 16, fiber: 5, sodium: 150 },
        'Gaspacho Tomates et Melon': { calories: 180, proteins: 8, carbs: 15, fats: 12, fiber: 6, sodium: 400 }
    };
    
    return nutritionMap[dish.name] || { calories: 350, proteins: 20, carbs: 15, fats: 20, fiber: 3, sodium: 800 };
}

function updateReviews(dish) {
    const reviewsTab = document.getElementById('reviews');
    if (reviewsTab) {
        const reviews = generateReviews(dish);
        reviewsTab.innerHTML = `
            <div class="reviews-list">
                ${reviews.map(review => `
                    <div class="review-item">
                        <div class="review-header">
                            <strong>${review.author}</strong>
                            <div class="review-stars">
                                ${generateStars(review.rating)}
                            </div>
                        </div>
                        <p>"${review.comment}"</p>
                        <small class="text-muted">${review.date}</small>
                    </div>
                `).join('')}
            </div>
            <div class="add-review-section mt-4">
                <div class="text-center">
                    <button id="showReviewFormBtn" class="btn btn-primary">
                        <i class="bi bi-chat-dots me-2"></i>Ajouter un avis
                    </button>
                </div>
            </div>
        `;
        
        // Setup modal functionality
        setupDishReviewModal(dish);
    }
}

function generateReviews(dish) {
    // Generate sample reviews based on dish
    const reviews = [
        {
            author: 'Marie L.',
            rating: 5,
            comment: 'Excellente qualité ! Authentique et savoureux, exactement comme attendu.',
            date: 'Il y a 2 jours'
        },
        {
            author: 'Jean-Pierre M.',
            rating: 4,
            comment: 'Très bon plat, ingrédients frais et service impeccable.',
            date: 'Il y a 1 semaine'
        },
        {
            author: 'Sophie D.',
            rating: 5,
            comment: 'Délicieux ! Je recommande vivement, à refaire absolument.',
            date: 'Il y a 2 semaines'
        }
    ];
    
    return reviews;
}

function generateStars(rating) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        if (i <= rating) {
            stars += '<i class="bi bi-star-fill text-warning"></i>';
        } else {
            stars += '<i class="bi bi-star text-warning"></i>';
        }
    }
    return stars;
}

function initQuantityControls(dish) {
    const decreaseBtn = document.getElementById('decreaseQty');
    const increaseBtn = document.getElementById('increaseQty');
    const quantityDisplay = document.getElementById('quantityDisplay');
    
    if (decreaseBtn && increaseBtn && quantityDisplay) {
        // Initialize quantity display with current cart quantity
        updateQuantityDisplay(dish.id);
        
        // Always show all buttons and quantity display
        showAllControls();
        
        decreaseBtn.addEventListener('click', function() {
            if (!this.disabled) {
                const currentQty = getItemQuantity(dish.id);
                if (currentQty > 0) {
                    removeFromCart(dish.id);
                    updateQuantityDisplay(dish.id);
                    
                    // Show notification
                    if (window.showNotification) {
                        if (currentQty === 1) {
                            window.showNotification(`${dish.name} supprimé du panier`, 'info');
                        } else {
                            window.showNotification('Quantité diminuée', 'success');
                        }
                    }
                }
            }
        });
        
        increaseBtn.addEventListener('click', function() {
            const currentQty = getItemQuantity(dish.id);
            addToCart(dish.id);
            updateQuantityDisplay(dish.id);
            
            // Show notification
            if (window.showNotification) {
                if (currentQty === 0) {
                    window.showNotification(`${dish.name} ajouté au panier`, 'success');
                } else {
                    window.showNotification('Quantité augmentée', 'success');
                }
            }
        });
    }
}

function showAllControls() {
    const decreaseBtn = document.getElementById('decreaseQty');
    const quantityDisplay = document.getElementById('quantityDisplay');
    
    if (decreaseBtn && quantityDisplay) {
        decreaseBtn.style.display = 'flex';
        quantityDisplay.style.display = 'block';
    }
}

function updateQuantityDisplay(itemId) {
    const quantityDisplay = document.getElementById('quantityDisplay');
    const decreaseBtn = document.getElementById('decreaseQty');
    
    if (quantityDisplay) {
        const quantity = getItemQuantity(itemId);
        quantityDisplay.textContent = quantity;
        
        // Enable/disable decrease button based on quantity
        if (decreaseBtn) {
            if (quantity > 0) {
                decreaseBtn.disabled = false;
                decreaseBtn.style.opacity = '1';
                decreaseBtn.style.cursor = 'pointer';
            } else {
                decreaseBtn.disabled = true;
                decreaseBtn.style.opacity = '0.5';
                decreaseBtn.style.cursor = 'not-allowed';
            }
        }
    }
}

function getItemQuantity(itemId) {
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const item = cart.find(item => item.id === itemId);
    return item ? item.quantity : 0;
}

function addToCart(itemId) {
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const existingItem = cart.find(item => item.id === itemId);
    
    if (existingItem) {
        existingItem.quantity += 1;
    } else {
        // Find item in menu data
        const menuItem = findItemById(itemId);
        if (menuItem) {
            cart.push({
                id: menuItem.id,
                name: menuItem.name,
                price: menuItem.price,
                quantity: 1
            });
        }
    }
    
    localStorage.setItem('cart', JSON.stringify(cart));
    
    // Update cart navigation and sidebar
    if (window.updateCartNavigation) {
        window.updateCartNavigation();
    }
    if (window.updateCartSidebar) {
        window.updateCartSidebar();
    }
    
    // Keep cart open when modifying quantities
    if (window.cartIsActive !== undefined) {
        window.cartIsActive = true;
        if (window.resetCartActiveState) {
            window.resetCartActiveState();
        }
    }
    
    // Dispatch custom event for cart updates
    window.dispatchEvent(new CustomEvent('cartUpdated'));
}

function removeFromCart(itemId) {
    const cart = JSON.parse(localStorage.getItem('cart') || '[]');
    const index = cart.findIndex(item => item.id === itemId);
    
    if (index !== -1) {
        const item = cart[index];
        item.quantity--;
        
        if (item.quantity <= 0) {
            cart.splice(index, 1);
            showNotification(`${item.name} supprimé du panier`, 'info');
        } else {
            showNotification('Quantité diminuée', 'success');
        }
        
        localStorage.setItem('cart', JSON.stringify(cart));
        
        // Update cart navigation and sidebar
        if (window.updateCartNavigation) {
            window.updateCartNavigation();
        }
        if (window.updateCartSidebar) {
            window.updateCartSidebar();
        }
        
        // Keep cart open when modifying quantities
        if (window.cartIsActive !== undefined) {
            window.cartIsActive = true;
            if (window.resetCartActiveState) {
                window.resetCartActiveState();
            }
        }
        
        // Dispatch custom event for cart updates
        window.dispatchEvent(new CustomEvent('cartUpdated'));
    }
}

function findItemById(itemId) {
    // Search in menuItems array
    if (window.menuItems) {
        return window.menuItems.find(item => item.id === itemId);
    }
    
    // Fallback: search in drinks data
    if (window.drinksData) {
        return window.drinksData.find(item => item.id === itemId);
    }
    
    return null;
}



function addCartUpdateListener(dish) {
    // Listen for storage changes (when cart is updated from other pages)
    window.addEventListener('storage', function(e) {
        if (e.key === 'cart') {
            updateQuantityDisplay(dish.id);
        }
    });
    
    // Listen for custom cart update events
    window.addEventListener('cartUpdated', function() {
        updateQuantityDisplay(dish.id);
    });
    
    // Set up interval to check for cart changes (fallback)
    setInterval(() => {
        updateQuantityDisplay(dish.id);
    }, 1000);
}

function loadRelatedDishes(currentDish) {
    const relatedContainer = document.querySelector('.related-dishes .row');
    if (relatedContainer && window.menuItems) {
        // Find related dishes (same category, different dish)
        const relatedDishes = window.menuItems
            .filter(item => item.category === currentDish.category && item.id !== currentDish.id)
            .slice(0, 3);
        
        relatedContainer.innerHTML = '';
        relatedDishes.forEach(dish => {
            const dishCard = `
                <div class="col-lg-4 mb-4">
                    <div class="dish-card">
                        <img src="${dish.image}" alt="${dish.name}" class="img-fluid">
                        <div class="dish-card-content">
                            <h5>${dish.name}</h5>
                            <p>${dish.description}</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="price">${dish.price}€</span>
                                <a href="dish-detail.html?id=${dish.id}" class="quick-view-btn">Voir détails</a>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            relatedContainer.innerHTML += dishCard;
        });
    }
}

// Setup dish review modal functionality
function setupDishReviewModal(dish) {
    const showFormBtn = document.getElementById('showReviewFormBtn');
    const modal = document.getElementById('reviewModal');
    const form = document.getElementById('dishReviewForm');
    const stars = document.querySelectorAll('.star-rating');
    const ratingInput = document.getElementById('ratingValue');
    const submitBtn = document.getElementById('submitReview');
    
    // Setup show modal functionality
    if (showFormBtn && modal) {
        showFormBtn.addEventListener('click', function() {
            const bootstrapModal = new bootstrap.Modal(modal);
            bootstrapModal.show();
        });
    }
    
    if (form && stars.length > 0) {
        // Setup rating stars
        stars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.dataset.rating);
                ratingInput.value = rating;
                
                // Update stars display
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.classList.remove('bi-star');
                        s.classList.add('bi-star-fill', 'filled');
                    } else {
                        s.classList.remove('bi-star-fill', 'filled');
                        s.classList.add('bi-star');
                    }
                });
            });
            
            star.addEventListener('mouseenter', function() {
                const rating = parseInt(this.dataset.rating);
                stars.forEach((s, index) => {
                    if (index < rating) {
                        s.classList.add('active');
                    }
                });
            });
            
            star.addEventListener('mouseleave', function() {
                stars.forEach(s => s.classList.remove('active'));
            });
        });
        
        // Setup form submission
        if (submitBtn) {
            submitBtn.addEventListener('click', function() {
                const name = document.getElementById('reviewerName').value.trim();
                const rating = parseInt(ratingInput.value);
                const text = document.getElementById('reviewText').value.trim();
                const email = document.getElementById('reviewEmail').value.trim();
                
                // Validation
                if (!name || !rating || !text) {
                    alert('Veuillez remplir tous les champs obligatoires.');
                    return;
                }
                
                if (rating === 0) {
                    alert('Veuillez sélectionner une note.');
                    return;
                }
                
                // Create new review
                const newReview = {
                    author: name,
                    rating: rating,
                    comment: text,
                    date: 'Aujourd\'hui',
                    email: email
                };
                
                // Add to reviews array (in a real app, this would be saved to server)
                const reviews = generateReviews(dish);
                reviews.unshift(newReview);
                
                // Update display
                updateReviews(dish);
                
                // Show success message
                showDishReviewSuccess('Votre avis a été ajouté avec succès !');
                
                // Close modal
                const bootstrapModal = bootstrap.Modal.getInstance(modal);
                if (bootstrapModal) {
                    bootstrapModal.hide();
                }
                
                // Reset form
                resetDishReviewForm();
            });
        }
    }
}

function resetDishReviewForm() {
    const form = document.getElementById('dishReviewForm');
    if (form) {
        form.reset();
    }
    
    const ratingInput = document.getElementById('ratingValue');
    if (ratingInput) {
        ratingInput.value = '0';
    }
    
    const stars = document.querySelectorAll('.star-rating');
    stars.forEach(star => {
        star.classList.remove('bi-star-fill', 'filled');
        star.classList.add('bi-star');
    });
}

function showDishReviewSuccess(message) {
    // Create success message element
    const successDiv = document.createElement('div');
    successDiv.className = 'success-message show';
    successDiv.innerHTML = `
        <i class="bi bi-check-circle me-2"></i>
        ${message}
    `;
    
    // Insert at the top of reviews container
    const reviewsTab = document.getElementById('reviews');
    if (reviewsTab) {
        reviewsTab.insertBefore(successDiv, reviewsTab.firstChild);
        
        // Remove after 5 seconds
        setTimeout(() => {
            successDiv.remove();
        }, 5000);
    }
}