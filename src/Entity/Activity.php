<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Entity;

use Carbon\CarbonImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineTrackBundle\Attribute\TrackColumn;
use Tourze\DoctrineUserBundle\Traits\BlameableAware;
use Tourze\RaffleCoreBundle\Repository\ActivityRepository;

/**
 * 抽奖活动
 */
#[ORM\Entity(repositoryClass: ActivityRepository::class)]
#[ORM\Table(name: 'raffle_activity', options: ['comment' => '抽奖活动'])]
#[ORM\Index(columns: ['valid', 'start_time', 'end_time'], name: 'raffle_activity_idx_activity_time_valid')]
#[ORM\Index(columns: ['start_time', 'end_time'], name: 'raffle_activity_idx_activity_time_range')]
class Activity implements \Stringable
{
    use TimestampableAware;
    use BlameableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '活动ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 120, options: ['comment' => '活动标题'])]
    #[Assert\NotBlank(message: '活动标题不能为空')]
    #[Assert\Length(max: 120, maxMessage: '活动标题不能超过{{ limit }}个字符')]
    #[TrackColumn]
    #[IndexColumn]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '活动描述'])]
    #[Assert\Length(max: 65535, maxMessage: '活动描述不能超过{{ limit }}个字符')]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 512, nullable: true, options: ['comment' => '活动图片'])]
    #[Assert\Length(max: 512, maxMessage: '活动图片路径不能超过{{ limit }}个字符')]
    #[Assert\Url(message: '请输入有效的图片URL')]
    private ?string $picture = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '活动开始时间'])]
    #[Assert\NotNull(message: '开始时间不能为空')]
    #[TrackColumn]
    #[IndexColumn]
    private \DateTimeImmutable $startTime;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['comment' => '活动结束时间'])]
    #[Assert\NotNull(message: '结束时间不能为空')]
    #[Assert\GreaterThan(propertyPath: 'startTime', message: '结束时间必须大于开始时间')]
    #[TrackColumn]
    #[IndexColumn]
    private \DateTimeImmutable $endTime;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否有效'])]
    #[Assert\Type(type: 'bool', message: '有效状态必须为布尔值')]
    #[TrackColumn]
    #[IndexColumn]
    private bool $valid = true;

    /**
     * @var Collection<int, Chance>
     */
    #[ORM\OneToMany(mappedBy: 'activity', targetEntity: Chance::class, cascade: ['persist', 'remove'])]
    private Collection $chances;

    /**
     * @var Collection<int, Pool>
     */
    #[ORM\ManyToMany(targetEntity: Pool::class, inversedBy: 'activities')]
    #[ORM\JoinTable(
        name: 'raffle_activity_pool',
        joinColumns: [new ORM\JoinColumn(name: 'activity_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'pool_id', referencedColumnName: 'id')]
    )]
    private Collection $pools;

    public function __construct()
    {
        $this->chances = new ArrayCollection();
        $this->pools = new ArrayCollection();
        $this->startTime = CarbonImmutable::now();
        $this->endTime = CarbonImmutable::now()->addDays(7);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getPicture(): ?string
    {
        return $this->picture;
    }

    public function setPicture(?string $picture): void
    {
        $this->picture = $picture;
    }

    public function getStartTime(): CarbonImmutable
    {
        return $this->startTime instanceof CarbonImmutable
            ? $this->startTime
            : CarbonImmutable::instance($this->startTime);
    }

    public function setStartTime(CarbonImmutable $startTime): void
    {
        $this->startTime = $startTime;
    }

    public function getEndTime(): CarbonImmutable
    {
        return $this->endTime instanceof CarbonImmutable
            ? $this->endTime
            : CarbonImmutable::instance($this->endTime);
    }

    public function setEndTime(CarbonImmutable $endTime): void
    {
        $this->endTime = $endTime;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): void
    {
        $this->valid = $valid;
    }

    /**
     * @return Collection<int, Chance>
     */
    public function getChances(): Collection
    {
        return $this->chances;
    }

    public function addChance(Chance $chance): void
    {
        if (!$this->chances->contains($chance)) {
            $this->chances->add($chance);
            $chance->setActivity($this);
        }
    }

    public function removeChance(Chance $chance): void
    {
        if ($this->chances->removeElement($chance)) {
            if ($chance->getActivity() === $this) {
                $chance->setActivity(null);
            }
        }
    }

    /**
     * @return Collection<int, Pool>
     */
    public function getPools(): Collection
    {
        return $this->pools;
    }

    public function addPool(Pool $pool): void
    {
        if (!$this->pools->contains($pool)) {
            $this->pools->add($pool);
        }
    }

    public function removePool(Pool $pool): void
    {
        $this->pools->removeElement($pool);
    }

    public function isActive(): bool
    {
        $now = CarbonImmutable::now();

        return $this->valid && $now->greaterThanOrEqualTo($this->startTime) && $now->lessThanOrEqualTo($this->endTime);
    }

    public function __toString(): string
    {
        return $this->title;
    }
}
