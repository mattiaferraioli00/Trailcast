<?php

namespace App\Tests\Application\Repository\ExcursionRepository;

use App\Tests\Application\ApplicationTestCase;
use App\Entity\Excursion;
use App\Http\Requests\SearchExcursionRequest;
use App\Repository\ExcursionRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\Tools\Pagination\WindowPage;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;

#[CoversMethod(ExcursionRepository::class, 'findBookable')]
class ExcursionRepositoryFindBookableTest extends ApplicationTestCase
{
    protected ExcursionRepository $repository;

    /**
     * Boots the kernel and reads the repository under test.
     */
    protected function setUp(): void
    {
        self::bootKernel();
        $this->prepareApplication();

        $this->repository = $this->entityManager->getRepository(Excursion::class);
    }

    /**
     * Given excursions in the past, sold out, and bookable (today included),
     * when the bookable ones are searched,
     * then the past and sold-out ones are left out.
     */
    #[Test]
    public function excludes_past_and_sold_out_excursions(): void
    {
        $this->createExcursion(['date' => new DateTime('-1 day')]);
        $this->createExcursion(['seatsAvailable' => 0]);
        $today = $this->createExcursion(['date' => new DateTime('today')]);
        $future = $this->createExcursion(['date' => new DateTime('+3 days')]);

        $page = $this->find(new SearchExcursionRequest());

        $this->assertSame([$today->getId(), $future->getId()], $this->ids($page->getItems()));
        $this->assertSame(2, $page->getTotalCount());
    }

    /**
     * Given excursions on the same and on different days,
     * when the bookable ones are searched,
     * then they are ordered by date and then by id.
     */
    #[Test]
    public function orders_by_date_then_id(): void
    {
        $late = $this->createExcursion(['date' => new DateTime('+6 days')]);
        $first = $this->createExcursion(['date' => new DateTime('+2 days')]);
        $second = $this->createExcursion(['date' => new DateTime('+2 days')]);

        $page = $this->find(new SearchExcursionRequest());

        $this->assertSame([$first->getId(), $second->getId(), $late->getId()], $this->ids($page->getItems()));
    }

    /**
     * Given excursions in different cities and countries,
     * when the search filters by city and country,
     * then only the excursions with that exact city and country are returned.
     */
    #[Test]
    public function filters_by_city_and_country(): void
    {
        $this->createExcursion(['city' => 'Catania', 'country' => 'IT']);
        $roma = $this->createExcursion(['city' => 'Roma', 'country' => 'IT']);
        $this->createExcursion(['city' => 'Roma', 'country' => 'US']);

        $page = $this->find(new SearchExcursionRequest(city: 'Roma', country: 'IT'));

        $this->assertSame([$roma->getId()], $this->ids($page->getItems()));
        $this->assertSame(1, $page->getTotalCount());
    }

    /**
     * Given excursions on different days,
     * when the search filters by date range,
     * then the range bounds are inclusive.
     */
    #[Test]
    public function filters_by_an_inclusive_date_range(): void
    {
        $this->createExcursion(['date' => new DateTime('+2 days')]);
        $from = $this->createExcursion(['date' => new DateTime('+4 days')]);
        $middle = $this->createExcursion(['date' => new DateTime('+5 days')]);
        $to = $this->createExcursion(['date' => new DateTime('+6 days')]);
        $this->createExcursion(['date' => new DateTime('+8 days')]);

        $page = $this->find(new SearchExcursionRequest(
            dateFrom: new DateTime('midnight +4 days'),
            dateTo: new DateTime('midnight +6 days'),
        ));

        $this->assertSame([$from->getId(), $middle->getId(), $to->getId()], $this->ids($page->getItems()));
    }

    /**
     * Given excursions with different seats left,
     * when the search filters by seats,
     * then excursions with at least that many seats are returned.
     */
    #[Test]
    public function filters_by_minimum_available_seats(): void
    {
        $this->createExcursion(['seatsAvailable' => 2]);
        $exact = $this->createExcursion(['seatsAvailable' => 5]);
        $more = $this->createExcursion(['seatsAvailable' => 9]);

        $page = $this->find(new SearchExcursionRequest(seats: 5));

        $this->assertSame([$exact->getId(), $more->getId()], $this->ids($page->getItems()));
    }

    /**
     * Given more matches than the page size,
     * when a page is requested,
     * then it holds only its slice while the total counts every match.
     */
    #[Test]
    public function paginates_and_counts_every_match(): void
    {
        $ids = [];
        for ($day = 1; $day <= 5; $day++) {
            $ids[] = $this->createExcursion(['date' => new DateTime("+$day days")])->getId();
        }

        $page = $this->find(new SearchExcursionRequest(page: 2, perPage: 2));

        $this->assertSame([$ids[2], $ids[3]], $this->ids($page->getItems()));
        $this->assertSame(5, $page->getTotalCount());
    }

    /**
     * Given a page past the last one,
     * when it is requested,
     * then it is empty but the total is still right.
     */
    #[Test]
    public function returns_an_empty_page_past_the_last_one(): void
    {
        $this->createExcursion();

        $page = $this->find(new SearchExcursionRequest(page: 3, perPage: 10));

        $this->assertSame([], $this->ids($page->getItems()));
        $this->assertSame(1, $page->getTotalCount());
    }

    /**
     * Runs the query with today as lower bound.
     *
     * @param SearchExcursionRequest $request Filters and pagination of the search.
     *
     * @return WindowPage The requested page.
     */
    protected function find(SearchExcursionRequest $request): WindowPage
    {
        return $this->repository->findBookable($request, new DateTimeImmutable('today'));
    }

    /**
     * Extracts the ids of the excursions of a page.
     *
     * @param iterable<Excursion> $excursions Excursions of a page.
     *
     * @return list<int> Their ids, in the same order.
     */
    protected function ids(iterable $excursions): array
    {
        $ids = [];
        foreach ($excursions as $excursion) {
            $ids[] = $excursion->getId();
        }

        return $ids;
    }
}
