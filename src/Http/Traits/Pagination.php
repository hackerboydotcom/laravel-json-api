<?php

namespace HackerBoy\LaravelJsonApi\Http\Traits;

use HackerBoy\LaravelJsonApi\Exceptions\JsonApiException;

trait Pagination {

    /**
     * Pagination handler
     * Use callback if present or use the default resolver
     *
     * @param $query
     */
    protected function handlePagination($query)
    {
        // Pagination callback
        if (isset($this->config['events']['collection.pagination']) and is_callable($this->config['events']['collection.pagination'])) {

            // Execute callback
            call_user_func_array($this->config['events']['collection.pagination'], [$this->modelClass, $query, $this]);
            return;
        }

        list($page, $limit, $skip) = $this->requestPagination();
        $query->take($limit)->skip($skip);
        return;
    }

    /**
     * Default pagination handler
     * Generate pagination data from request
     *
     * @return array
     */
    public function requestPagination()
    {
        $request = $this->request;

        $resultLimit = @intval($this->config['result_limit']);
        $resultLimit = $resultLimit > 0 ? $resultLimit : 20;

        $maximumResultLimit = @intval($this->config['maximum_result_limit']);
        $maximumResultLimit = $maximumResultLimit > 0 ? $maximumResultLimit : 100;

        $page = intval($request->query('page'));
        $page = ($page > 1) ? $page : 1;
        $limit = intval($request->query('limit'));
        $limit = ($limit >= 1 and $limit <= $maximumResultLimit) ? $limit : $resultLimit;

        return [$page, $limit, ($page-1)*$limit];
    }

    /**
     * Handle response pagination
     *
     * @param $query
     */
    protected function responsePagination($query)
    {
        list($page, $limit, $offset) = $this->requestPagination();

        // Count
        $count = $query->count();

        // Http query
        $httpQuery = $this->request->query();

        // Get resource type
        $newModel = new $this->modelClass;
        $resourceInstance = $this->document->getResource($newModel);
        $resourceType = $resourceInstance->getType($newModel);
        unset($newModel);

        $baseLink = $this->document->getUrl($resourceType);

        // First link
        $firstLinkQuery = $httpQuery;
        $firstLinkQuery['page'] = 1;
        $firstLink = $baseLink.'?'.http_build_query($firstLinkQuery);

        // Last link
        $calculatePage = $limit > 0 ? ($count / $limit) : 0;
        $calculatePage = $calculatePage > 1 ? $calculatePage : 1;
        $calculatePageRounded = intval($calculatePage);
        $calculatePage = ($calculatePage > $calculatePageRounded) ? $calculatePageRounded+1 : $calculatePageRounded;

        // Self link
        $selfLinkQuery = $httpQuery;
        $selfLinkQuery['page'] = $page;
        $selfLink = $baseLink.'?'.http_build_query($selfLinkQuery);

        // Create pagination
        $pagination = [
            'self' => $selfLink,
        ];

        // If current link is not the first
        if ($page > 1) {
            $pagination['first'] = $firstLink;
        }

        // If has last link
        $lastLinkQuery = $httpQuery;
        $lastLinkQuery['page'] = $calculatePage;
        $lastLink = $baseLink.'?'.http_build_query($lastLinkQuery);
        $pagination['last'] = $lastLink;

        // Prev link
        if ($page > 1) {
            $prevLinkQuery = $httpQuery;
            $prevLinkQuery['page']--;
            $prevLink = $baseLink.'?'.http_build_query($prevLinkQuery);
            $pagination['prev'] = $prevLink;
        }

        // Next link
        if ($page < $calculatePage) {

            $nextLinkQuery = $httpQuery;

            if (!isset($nextLinkQuery['page'])) {
                $nextLinkQuery['page'] = 1;
            }

            $nextLinkQuery['page']++;
            $nextLink = $baseLink.'?'.http_build_query($nextLinkQuery);
            $pagination['next'] = $nextLink;
        }

        foreach ($pagination as $key => $value) {
            $pagination[$key] = urldecode($value);
        }

        $pagination = $this->document->makePagination($pagination);

        $this->document->setLinks($pagination);
    }
}
