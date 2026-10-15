<?php

namespace App\Repository;

use App\Entity\Excursion;
use App\Http\Requests\SearchExcursionRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Tools\Pagination\OffsetPaginator;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\Tools\Pagination\Window;
use Doctrine\ORM\Tools\Pagination\WindowPage;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Excursion>
 */
class ExcursionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Excursion::class);
    }

    /**
     * Finds the excursions that can still be booked (capacity left and date from today on), applying the optional
     * filters of the request, ordered by date and id.
     *
     * @param SearchExcursionRequest $request Optional filters (city, country, date range, seats) and pagination.
     * @param \DateTimeImmutable $today Lower bound of the excursion date (start of the current day).
     * @return WindowPage The requested page of excursions, with the total number of matches.
     */
    public function findBookable(SearchExcursionRequest $request, \DateTimeImmutable $today): WindowPage
    {
        $query = $this->createQueryBuilder('e')
            ->where('e.seats_available > 0')
            ->andWhere('e.date >= :today')
            ->setParameter('today', $today, Types::DATE_IMMUTABLE);

        // TODO: Se c'è tempo implementare ricerca per zona
        if (!is_null($request->getCity())) {
            $query->andWhere('e.city = :city')
                ->setParameter('city', $request->getCity());
        }

        if (!is_null($request->getCountry())) {
            $query->andWhere('e.country = :country')
                ->setParameter('country', $request->getCountry());
        }

        if (!is_null($request->getDateFrom())) {
            $query->andWhere('e.date >= :from')
                ->setParameter('from', $request->getDateFrom());
        }

        if (!is_null($request->getDateTo())) {
            $query->andWhere('e.date <= :to')
                ->setParameter('to', $request->getDateTo());
        }

        if (!is_null($request->getSeats())) {
            $query->andWhere('e.seats_available >= :seats')
                ->setParameter('seats', $request->getSeats());
        }

        $query->orderBy('e.date', 'ASC')
            ->addOrderBy('e.id', 'ASC');

        return (new OffsetPaginator(fetchJoinCollection: false))
            ->paginate($query, Window::fromPageNumberAndSize($request->getPage(), $request->getPerPage()));
    }

//    /**
//     * @return Excursion[] Returns an array of Excursion objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('e.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Excursion
//    {
//        return $this->createQueryBuilder('e')
//            ->andWhere('e.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
