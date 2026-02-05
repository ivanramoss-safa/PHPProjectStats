<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Ranking;
use App\Entity\RankingItem;
use App\Repository\CategoryRepository;
use App\Repository\RankingRepository;
use App\Service\api\FootballApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/ranking')]
#[IsGranted('ROLE_USER')]
class RankingController extends AbstractController
{



    #[Route('/', name: 'app_ranking_index')]
    public function index(RankingRepository $rankingRepository): Response
    {
        $user = $this->getUser();
        $rankings = $rankingRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('ranking/index.html.twig', [
            'rankings' => $rankings,
        ]);
    }



    #[Route('/new', name: 'app_ranking_new')]
    public function new(CategoryRepository $categoryRepository): Response
    {
        $categories = $categoryRepository->findAll();

        return $this->render('ranking/new.html.twig', [
            'categories' => $categories,
        ]);
    }



    #[Route('/create/{id}', name: 'app_ranking_create')]
    public function create(
        Category $category,
        Request $request,
        FootballApiService $apiService,
        EntityManagerInterface $em
    ): Response {

        $query = $request->query->get('q', '');
        $page = (int) $request->query->get('page', 1);

        $apiResults = [];
        $paging = ['current' => 1, 'total' => 1];

        if (!empty($query) || $category->getCategoryItems()->count() > 0) {
            $data = $apiService->getItemsFromCategory($category, $query, $page);
            $apiResults = $data['response'] ?? [];
            $paging = $data['paging'] ?? ['current' => 1, 'total' => 1];
        }

        $items = [];
        switch ($category->getType()) {
            case 'player':
                foreach ($apiResults as $result) {
                    $clubTeam = 'Sin equipo';
                    $maxMinutes = -1;
                    $totalGoals = 0;
                    
                    foreach ($result['statistics'] ?? [] as $stat) {
                        $totalGoals += $stat['goals']['total'] ?? 0;



                        if (($stat['league']['type'] ?? '') === 'League') {
                             $minutes = $stat['games']['minutes'] ?? 0;
                             if ($minutes > $maxMinutes) {
                                 $maxMinutes = $minutes;
                                 $clubTeam = $stat['team']['name'] ?? 'Sin equipo';
                             }
                        }
                    }

                    if ($clubTeam === 'Sin equipo' && !empty($result['statistics'][0]['team']['name'])) {
                        $clubTeam = $result['statistics'][0]['team']['name'];
                    }
                    $items[] = [
                        'id' => $result['player']['id'] ?? null,
                        'name' => $result['player']['name'] ?? 'Sin nombre',
                        'photo' => $result['player']['photo'] ?? null,
                        'extra' => $clubTeam,
                        'stats' => $totalGoals . ' goles',
                    ];
                }
                break;
            case 'team':
                foreach ($apiResults as $result) {
                    $items[] = [
                        'id' => $result['team']['id'] ?? null,
                        'name' => $result['team']['name'] ?? 'Sin nombre',
                        'photo' => $result['team']['logo'] ?? null,
                        'extra' => $result['team']['country'] ?? '',
                        'stats' => $result['team']['founded'] ?? '',
                    ];
                }
                break;

            case 'league':
                foreach ($apiResults as $result) {
                    $items[] = [
                        'id' => $result['league']['id'] ?? null,
                        'name' => $result['league']['name'] ?? 'Sin nombre',
                        'photo' => $result['league']['logo'] ?? null,
                        'extra' => $result['country']['name'] ?? '',
                        'stats' => $result['league']['type'] ?? '',
                    ];
                }
                break;

            case 'stadium':
                foreach ($apiResults as $result) {
                    $items[] = [
                        'id' => $result['id'] ?? null,
                        'name' => $result['name'] ?? 'Sin nombre',
                        'photo' => $result['image'] ?? null,
                        'extra' => $result['city'] ?? '',
                        'stats' => ($result['capacity'] ?? 0) . ' personas',
                    ];
                }
                break;

            case 'coach':
                foreach ($apiResults as $result) {
                    $items[] = [
                        'id' => $result['id'] ?? null,
                        'name' => $result['name'] ?? 'Sin nombre',
                        'photo' => $result['photo'] ?? null,
                        'extra' => $result['nationality'] ?? '',
                        'stats' => $result['age'] ?? '',
                    ];
                }
                break;
        }

        return $this->render('ranking/create.html.twig', [
            'category' => $category,
            'items' => $items,
            'query' => $query,
            'paging' => $paging,
        ]);
    }



    #[Route('/order/{id}', name: 'app_ranking_order')]
    public function order(
        Category $category,
        Request $request,
        FootballApiService $apiService
    ): Response {

        $selectedIds = $request->request->all('selected_items');

        if (empty($selectedIds)) {
            $this->addFlash('error', 'Debes seleccionar al menos un elemento.');
            return $this->redirectToRoute('app_ranking_create', ['id' => $category->getId()]);
        }

        if (count($selectedIds) > 10) {
            $this->addFlash('error', 'Solo puedes seleccionar hasta 10 elementos.');
            return $this->redirectToRoute('app_ranking_create', ['id' => $category->getId()]);
        }

        $selectedItems = [];
        foreach ($selectedIds as $externalId) {

            $itemData = null;
            switch ($category->getType()) {
                case 'player':
                    $data = $apiService->getPlayerStatistics((int)$externalId, 2024);
                    if (!empty($data['response'][0])) {
                        $result = $data['response'][0];
                        $itemData = [
                            'id' => $result['player']['id'],
                            'name' => $result['player']['name'],
                            'photo' => $result['player']['photo'] ?? null,
                            'extra' => $result['statistics'][0]['team']['name'] ?? '',
                        ];
                    }
                    break;

                case 'team':
                    $data = $apiService->getTeamById((int)$externalId);
                    if (!empty($data['response'][0])) {
                        $result = $data['response'][0];
                        $itemData = [
                            'id' => $result['team']['id'],
                            'name' => $result['team']['name'],
                            'photo' => $result['team']['logo'] ?? null,
                            'extra' => $result['team']['country'] ?? '',
                        ];
                    }
                    break;

                case 'league':
                    $data = $apiService->getLeagueById((int)$externalId);
                    if (!empty($data['response'][0])) {
                        $result = $data['response'][0];
                        $itemData[] = [
                            'id' => $result['league']['id'] ?? null,
                            'name' => $result['league']['name'] ?? 'Sin nombre',
                            'photo' => $result['league']['logo'] ?? null,
                            'extra' => $result['country']['name'] ?? '',
                        ];
                    }
                    break;

                case 'stadium':
                    $data = $apiService->getVenueById((int)$externalId);
                    if (!empty($data['response'][0])) {
                        $result = $data['response'][0];
                        $itemData[] = [
                            'id' => $result['id'] ?? null,
                            'name' => $result['name'] ?? 'Sin nombre',
                            'photo' => $result['image'] ?? null,
                            'extra' => $result['city'] ?? '',
                            'stats' => ($result['capacity'] ?? 0) . ' personas',
                        ];
                    }
                    break;

                case 'coach':
                    $data = $apiService->getCoachById((int)$externalId);
                    if (!empty($data['response'][0])) {
                        $result = $data['response'][0];
                        $itemData[] = [
                            'id' => $result['id'] ?? null,
                            'name' => $result['name'] ?? 'Sin nombre',
                            'photo' => $result['photo'] ?? null,
                            'extra' => $result['nationality'] ?? '',
                            'stats' => $result['age'] ?? '',
                        ];
                    }
                    break;
            }

            if ($itemData) {
                $selectedItems[] = $itemData;
            }
        }

        return $this->render('ranking/order.html.twig', [
            'category' => $category,
            'items' => $selectedItems,
        ]);
    }



    #[Route('/save', name: 'app_ranking_save', methods: ['POST'])]
    public function save(
        Request $request,
        EntityManagerInterface $em,
        CategoryRepository $categoryRepository
    ): Response {
        $categoryId = $request->request->get('category_id');
        $rankingName = $request->request->get('ranking_name');
        $orderedItems = $request->request->all('ordered_items'); 

        if (empty($categoryId) || empty($rankingName) || empty($orderedItems)) {
            $this->addFlash('error', 'Datos incompletos.');
            return $this->redirectToRoute('app_ranking_new');
        }

        $category = $categoryRepository->find($categoryId);
        if (!$category) {
            throw $this->createNotFoundException('Categoría no encontrada.');
        }

        $ranking = new Ranking();
        $ranking->setUser($this->getUser());
        $ranking->setCategory($category);
        $ranking->setName($rankingName);

        $em->persist($ranking);

        $position = 1;
        foreach ($orderedItems as $externalId) {
            $rankingItem = new RankingItem();
            $rankingItem->setRanking($ranking);
            $rankingItem->setExternalId((int)$externalId);
            $rankingItem->setPosition($position);

            $em->persist($rankingItem);
            $position++;
        }

        $em->flush();

        $this->addFlash('success', '¡Ranking creado correctamente!');
        return $this->redirectToRoute('app_ranking_index');
    }



    #[Route('/{id}', name: 'app_ranking_show')]
    public function show(Ranking $ranking, FootballApiService $apiService): Response
    {

        if ($ranking->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $rankingItems = $ranking->getRankingItems()->toArray();
        usort($rankingItems, fn($a, $b) => $a->getPosition() <=> $b->getPosition());

        $items = [];
        foreach ($rankingItems as $rankingItem) {
            $itemData = null;
            $externalId = $rankingItem->getExternalId();

            switch ($ranking->getCategory()->getType()) {
                case 'player':
                    $data = $apiService->getPlayerStatistics($externalId, 2024);
                    if (!empty($data['response'][0])) {
                        $result = $data['response'][0];
                        $itemData = [
                            'position' => $rankingItem->getPosition(),
                            'name' => $result['player']['name'],
                            'photo' => $result['player']['photo'] ?? null,
                            'extra' => $result['statistics'][0]['team']['name'] ?? '',
                        ];
                    }
                    break;

                case 'team':
                    $data = $apiService->getTeamById($externalId);
                    if (!empty($data['response'][0])) {
                        $result = $data['response'][0];
                        $itemData = [
                            'position' => $rankingItem->getPosition(),
                            'name' => $result['team']['name'],
                            'photo' => $result['team']['logo'] ?? null,
                            'extra' => $result['team']['country'] ?? '',
                        ];
                    }
                    break;

                case 'league':
                    $data = $apiService->getLeagueById($externalId);
                    if (!empty($data['response'][0])) {
                        $result = $data['response'][0];
                        $itemData = [
                            'position' => $rankingItem->getPosition(),
                            'name' => $result['league']['name'] ?? 'Sin nombre',
                            'photo' => $result['league']['logo'] ?? null,
                            'extra' => $result['country']['name'] ?? '',
                        ];
                    }
                    break;

                case 'stadium':
                    $data = $apiService->getVenueById($externalId);
                    if (!empty($data['response'][0])) {
                        $result = $data['response'][0];
                        $itemData = [
                            'position' => $rankingItem->getPosition(),
                            'name' => $result['name'] ?? 'Sin nombre',
                            'photo' => $result['image'] ?? null,
                            'extra' => $result['city'] ?? '',
                            'stats' => ($result['capacity'] ?? 0) . ' personas',
                        ];
                    }
                    break;

                case 'coach':
                    $data = $apiService->getCoachById($externalId);
                    if (!empty($data['response'][0])) {
                        $result = $data['response'][0];
                        $itemData = [
                            'position' => $rankingItem->getPosition(),
                            'name' => $result['name'] ?? 'Sin nombre',
                            'photo' => $result['photo'] ?? null,
                            'extra' => $result['nationality'] ?? '',
                            'stats' => $result['age'] ?? '',
                        ];
                    }
                    break;
            }

            if ($itemData) {
                $items[] = $itemData;
            }
        }

        return $this->render('ranking/show.html.twig', [
            'ranking' => $ranking,
            'items' => $items,
        ]);
    }
}
