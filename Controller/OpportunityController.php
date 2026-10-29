<?php
class OpportunityController
{
    private DataRepository $repository;

    public function __construct(DataRepository $repository)
    {
        $this->repository = $repository;
    }

    public function search(array $filters): array
    {
        $page = isset($filters['page']) && is_scalar($filters['page']) ? max(1, (int) $filters['page']) : 1;
        $search = $this->repository->getOpportunitySearchPage($filters, $page);
        return [
            'success' => true,
            'results' => $search['items'],
            'count' => $search['total'],
            'page' => $search['page'],
            'pages' => $search['pages'],
        ];
    }
}
