<?php


namespace app\service;


use app\enum\SubscriptionPlan;
use app\repository\FileRepository;


class StorageService {
    private const FREE_SPACE = 104857600;
    private const BASE_SPACE = 10737418240;
    private const PREMIUM_SPACE = 107374182400;
    private const ULTIMATE_SPACE = 1099511627776;

    function __construct(
            private readonly UserService $userService,
            private readonly FileRepository $fileRepository
    ) {
    }

    function getUserTotalSpace(int $userId): int {
        return match ($this->userService::getSubscriptionPlan($userId)) {
            SubscriptionPlan::Base => StorageService::BASE_SPACE,
            SubscriptionPlan::Premium => StorageService::PREMIUM_SPACE,
            SubscriptionPlan::Ultimate => StorageService::ULTIMATE_SPACE,
            default => StorageService::FREE_SPACE,
        };
    }

    /**
     * @throws ServiceException
     */
    function getUserUsedSpace(int $userId): int {
        try {
            return $this->fileRepository->calculateUserUsedSpace($userId);
        } catch (\Throwable $e) {
            throw new ServiceException(message: 'Failed to calculate used space', previous: $e);
        }
    }
}
