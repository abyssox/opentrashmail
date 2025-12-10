<?php
declare(strict_types=1);

namespace OpenTrashmail\Controllers;

final class AppController
{
    private ApiController $apiController;
    private RssController $rssController;
    private JsonController $jsonController;

    /**
     * @param array<string,mixed> $settings
     */
    public function __construct(array $settings)
    {
        $this->apiController = new ApiController($settings);
        $this->rssController = new RssController($settings);
        $this->jsonController = new JsonController($settings);
    }

    /**
     * @param string $routeName Name assigned in FastRoute
     * @param array<string,mixed> $vars Route parameters from FastRoute
     *
     * @return string|null
     */
    public function handle(string $routeName, array $vars = []): ?string
    {
        if (str_starts_with($routeName, 'api_')) {
            return $this->apiController->handle($routeName, $vars);
        }

        if ($routeName === 'rss') {
            return $this->rssController->handle($vars);
        }

        if (str_starts_with($routeName, 'json_')) {
            return $this->jsonController->handle($routeName, $vars);
        }

        http_response_code(404);
        return '404 Not Found';
    }
}
