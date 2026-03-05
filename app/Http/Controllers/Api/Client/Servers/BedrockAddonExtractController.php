<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Access\AuthorizationException;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Permission;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Services\Minecraft\BedrockAddonInfoExtractor;

class BedrockAddonExtractController extends ClientApiController
{
    public function extract(Request $request, Server $server, DaemonFileRepository $fileRepository): JsonResponse
    {
        if (!$request->user()->can(Permission::ACTION_FILE_READ, $server)) {
            throw new AuthorizationException();
        }

        $validated = $request->validate([
            'packPath' => 'required|string',
        ]);

        $fileRepository->setServer($server);
        $extractor = new BedrockAddonInfoExtractor();

        try {
            $info = $extractor->extractAddonInfo($fileRepository, $validated['packPath']);
            return new JsonResponse($info);
        } catch (\Exception $e) {
            Log::error('Failed to extract addon info: ' . $e->getMessage());
            return new JsonResponse([
                'name' => null,
                'author' => 'Unknown Author',
                'summary' => '',
                'thumbnailUrl' => null,
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
