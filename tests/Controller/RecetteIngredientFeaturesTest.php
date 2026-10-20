<?php

namespace App\Tests\Controller;

use App\Entity\Ingredient;
use App\Entity\Recette;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RecetteIngredientFeaturesTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        if ($user) {
            $this->client->loginUser($user);
        }
    }

    public function testRecipeFormHasCreateIngredientButtonWithReturnTo(): void
    {
        $crawler = $this->client->request('GET', '/recette');
        $this->assertResponseIsSuccessful();

        // Le bouton Créer un ingrédient doit exister
        $btn = $crawler->filter('#create-ingredient-btn');
        $this->assertGreaterThan(0, $btn->count(), 'Le bouton #create-ingredient-btn doit être présent.');
        $this->assertStringContainsString('Créer un ingrédient', $btn->text());

        // Son lien doit pointer vers app_ingredient_create avec return_to=/recette
        $href = $btn->attr('href');
        $this->assertStringContainsString('/ingredient', $href);
        $this->assertStringContainsString('return_to=', $href);
        $this->assertTrue(str_contains($href, 'return_to=/recette') || str_contains($href, 'return_to=%2Frecette'));
    }

    public function testIngredientsInRecipeFormAreSortedAlphabetically(): void
    {
        // Créer 3 ingrédients avec des noms garantissant un ordre A, B, C
        $suffix = uniqid();
        $ingB = new Ingredient();
        $ingB->setDesignation('B_Ingrédient_' . $suffix);
        $this->em->persist($ingB);

        $ingA = new Ingredient();
        $ingA->setDesignation('A_Ingrédient_' . $suffix);
        $this->em->persist($ingA);

        $ingC = new Ingredient();
        $ingC->setDesignation('C_Ingrédient_' . $suffix);
        $this->em->persist($ingC);

        $this->em->flush();

        $crawler = $this->client->request('GET', '/recette');
        $this->assertResponseIsSuccessful();

        // Extraire les options du prototype ou du premier sélecteur
        $prototypeHtml = $crawler->filter('#ingredients-wrapper')->attr('data-prototype');
        $this->assertNotNull($prototypeHtml);

        // Parser le prototype HTML
        $protoCrawler = new \Symfony\Component\DomCrawler\Crawler($prototypeHtml);
        $options = $protoCrawler->filter('select.select-ingredient option')->each(function ($node) {
            return trim($node->text());
        });

        // Filtrer sur nos ingrédients de test
        $matching = array_values(array_filter($options, function ($text) use ($suffix) {
            return str_contains($text, $suffix);
        }));

        $this->assertSame([
            'A_Ingrédient_' . $suffix,
            'B_Ingrédient_' . $suffix,
            'C_Ingrédient_' . $suffix,
        ], $matching, 'Les ingrédients dans le sélecteur doivent être classés par ordre alphabétique.');
    }

    public function testCreateIngredientFlowReturnsToRecipe(): void
    {
        $returnUrl = '/recette';
        $crawler = $this->client->request('GET', '/ingredient?return_to=' . urlencode($returnUrl));
        $this->assertResponseIsSuccessful();

        // Vérifier la présence du champ caché return_to
        $hiddenReturnTo = $crawler->filter('input[type="hidden"][name="return_to"]');
        $this->assertGreaterThan(0, $hiddenReturnTo->count());
        $this->assertSame($returnUrl, $hiddenReturnTo->attr('value'));

        // Vérifier les boutons d'annulation et retour
        $this->assertSelectorTextContains('.page-header a.btn-secondary', 'Retour à la recette');

        // Soumettre le formulaire de création d'ingrédient
        $form = $crawler->selectButton('Créer l\'ingrédient')->form([
            'ingredient[designation]' => 'Nouveau Ingrédient Test ' . uniqid(),
            'return_to' => $returnUrl,
        ]);

        $this->client->submit($form);

        // Doit rediriger vers return_to avec new_ingredient_id
        $this->assertResponseRedirects();
        $redirectUrl = $this->client->getResponse()->headers->get('Location');
        $this->assertNotNull($redirectUrl);
        $this->assertStringStartsWith('/recette', $redirectUrl);
        $this->assertStringContainsString('new_ingredient_id=', $redirectUrl);

        // Suivre la redirection
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.alert-success', 'Ingrédient créé avec succès');
    }
}
