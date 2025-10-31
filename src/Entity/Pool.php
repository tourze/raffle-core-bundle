<?php

declare(strict_types=1);

namespace Tourze\RaffleCoreBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineTrackBundle\Attribute\TrackColumn;
use Tourze\DoctrineUserBundle\Traits\BlameableAware;
use Tourze\RaffleCoreBundle\Repository\PoolRepository;

/**
 * 奖池
 */
#[ORM\Entity(repositoryClass: PoolRepository::class)]
#[ORM\Table(name: 'raffle_pool', options: ['comment' => '奖池'])]
#[ORM\Index(columns: ['is_default', 'valid'], name: 'raffle_pool_idx_pool_default_valid')]
class Pool implements \Stringable
{
    use TimestampableAware;
    use BlameableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '奖池ID'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '奖池名称'])]
    #[Assert\NotBlank(message: '奖池名称不能为空')]
    #[Assert\Length(max: 255, maxMessage: '奖池名称长度不能超过 {{ limit }} 字符')]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '奖池描述'])]
    #[Assert\Length(max: 65535, maxMessage: '奖池描述长度不能超过 {{ limit }} 字符')]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '动态概率表达式'])]
    #[Assert\Length(max: 65535, maxMessage: '动态概率表达式长度不能超过 {{ limit }} 字符')]
    private ?string $probabilityExpression = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否兜底奖池'])]
    #[Assert\Type(type: 'bool', message: '兜底奖池标识必须为布尔值')]
    private bool $isDefault = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否启用'])]
    #[Assert\Type(type: 'bool', message: '启用状态必须为布尔值')]
    #[TrackColumn]
    #[IndexColumn]
    private bool $valid = true;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '排序号'])]
    #[Assert\GreaterThanOrEqual(value: 0, message: '排序号不能为负数')]
    #[Assert\PositiveOrZero(message: '排序号必须为非负数')]
    #[IndexColumn]
    private int $sortNumber = 0;

    /**
     * @var Collection<int, Award>
     */
    #[ORM\OneToMany(targetEntity: Award::class, mappedBy: 'pool', cascade: ['persist', 'remove'])]
    private Collection $awards;

    /**
     * @var Collection<int, Activity>
     */
    #[ORM\ManyToMany(targetEntity: Activity::class, mappedBy: 'pools')]
    private Collection $activities;

    public function __construct()
    {
        $this->awards = new ArrayCollection();
        $this->activities = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getProbabilityExpression(): ?string
    {
        return $this->probabilityExpression;
    }

    public function setProbabilityExpression(?string $probabilityExpression): void
    {
        $this->probabilityExpression = $probabilityExpression;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): void
    {
        $this->isDefault = $isDefault;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): void
    {
        $this->valid = $valid;
    }

    public function getSortNumber(): int
    {
        return $this->sortNumber;
    }

    public function setSortNumber(int $sortNumber): void
    {
        $this->sortNumber = $sortNumber;
    }

    /**
     * @return Collection<int, Award>
     */
    public function getAwards(): Collection
    {
        return $this->awards;
    }

    public function addAward(Award $award): void
    {
        if (!$this->awards->contains($award)) {
            $this->awards->add($award);
            $award->setPool($this);
        }
    }

    public function removeAward(Award $award): void
    {
        if ($this->awards->removeElement($award)) {
            if ($award->getPool() === $this) {
                $award->setPool(null);
            }
        }
    }

    /**
     * @return Collection<int, Activity>
     */
    public function getActivities(): Collection
    {
        return $this->activities;
    }

    public function addActivity(Activity $activity): void
    {
        if (!$this->activities->contains($activity)) {
            $this->activities->add($activity);
            $activity->addPool($this);
        }
    }

    public function removeActivity(Activity $activity): void
    {
        if ($this->activities->removeElement($activity)) {
            $activity->removePool($this);
        }
    }

    public function isAvailable(): bool
    {
        return $this->valid && $this->awards->count() > 0;
    }

    public function __toString(): string
    {
        return '' !== $this->name ? $this->name : '未命名奖池';
    }
}
