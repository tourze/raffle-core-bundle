<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Entity;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineTrackBundle\Attribute\TrackColumn;
use Tourze\DoctrineUserBundle\Traits\BlameableAware;
use Tourze\RaffleCoreBundle\Enum\ChanceStatusEnum;
use Tourze\RaffleCoreBundle\Repository\ChanceRepository;

/**
 * 抽奖机会
 */
#[ORM\Entity(repositoryClass: ChanceRepository::class)]
#[ORM\Table(name: 'raffle_chance', options: ['comment' => '抽奖机会'])]
#[ORM\Index(columns: ['user_id', 'status'])]
#[ORM\Index(columns: ['activity_id', 'status'])]
#[ORM\Index(columns: ['activity_id', 'user_id', 'status'], name: 'raffle_chance_idx_chance_activity_user_status')]
class Chance implements \Stringable
{
    use TimestampableAware;
    use BlameableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '机会ID'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Activity::class, inversedBy: 'chances', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: '活动不能为空')]
    private ?Activity $activity = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: '用户不能为空')]
    private ?UserInterface $user = null;

    #[ORM\Column(type: Types::STRING, enumType: ChanceStatusEnum::class, options: ['comment' => '状态'])]
    #[Assert\Choice(callback: [ChanceStatusEnum::class, 'cases'], message: '状态值无效')]
    #[TrackColumn]
    #[IndexColumn]
    private ChanceStatusEnum $status = ChanceStatusEnum::INIT;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '使用时间'])]
    #[Assert\DateTime(message: '使用时间格式无效')]
    #[IndexColumn]
    private ?\DateTimeImmutable $useTime = null;

    #[ORM\ManyToOne(targetEntity: Award::class, inversedBy: 'chances', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    private ?Award $award = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '中奖上下文'])]
    #[Assert\Type(type: 'array', message: '中奖上下文必须为数组类型')]
    private ?array $winContext = null;

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '乐观锁版本号'])]
    #[Assert\PositiveOrZero(message: '版本号必须为非负数')]
    private ?int $lockVersion = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActivity(): ?Activity
    {
        return $this->activity;
    }

    public function setActivity(?Activity $activity): void
    {
        $this->activity = $activity;
    }

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(?UserInterface $user): void
    {
        $this->user = $user;
    }

    public function getStatus(): ChanceStatusEnum
    {
        return $this->status;
    }

    public function setStatus(ChanceStatusEnum $status): void
    {
        $this->status = $status;
    }

    public function getUseTime(): ?CarbonImmutable
    {
        return null !== $this->useTime ? CarbonImmutable::instance($this->useTime) : null;
    }

    public function setUseTime(?CarbonImmutable $useTime): void
    {
        $this->useTime = $useTime;
    }

    public function getAward(): ?Award
    {
        return $this->award;
    }

    public function setAward(?Award $award): void
    {
        $this->award = $award;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getWinContext(): ?array
    {
        return $this->winContext;
    }

    /**
     * @param array<string, mixed>|null $winContext
     */
    public function setWinContext(?array $winContext): void
    {
        $this->winContext = $winContext;
    }

    public function getLockVersion(): ?int
    {
        return $this->lockVersion;
    }

    public function setLockVersion(?int $lockVersion): void
    {
        $this->lockVersion = $lockVersion;
    }

    public function isWinning(): bool
    {
        return null !== $this->award && ChanceStatusEnum::WINNING === $this->status;
    }

    public function canOrder(): bool
    {
        return $this->isWinning();
    }

    public function isExpired(): bool
    {
        return ChanceStatusEnum::EXPIRED === $this->status;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function markAsWinning(Award $award, array $context = []): void
    {
        $this->setAward($award);
        $this->setStatus(ChanceStatusEnum::WINNING);
        $this->setUseTime(CarbonImmutable::now());
        $this->setWinContext($context);
    }

    public function markAsOrdered(): void
    {
        $this->setStatus(ChanceStatusEnum::ORDERED);
    }

    public function markAsExpired(): void
    {
        $this->setStatus(ChanceStatusEnum::EXPIRED);
    }

    public function __toString(): string
    {
        $userName = $this->user?->getUserIdentifier() ?? 'Unknown';
        $activityTitle = $this->activity?->getTitle() ?? 'Unknown';

        return "{$userName} - {$activityTitle} - {$this->status->getLabel()}";
    }
}
