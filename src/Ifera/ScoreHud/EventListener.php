<?php
declare(strict_types = 1);

namespace Ifera\ScoreHud;

use Ifera\ScoreHud\event\PlayerTagsUpdateEvent;
use Ifera\ScoreHud\event\PlayerTagUpdateEvent;
use Ifera\ScoreHud\event\ServerTagsUpdateEvent;
use Ifera\ScoreHud\event\ServerTagUpdateEvent;
use Ifera\ScoreHud\scoreboard\ScoreTag;
use Ifera\ScoreHud\session\PlayerManager;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use function is_null;

class EventListener implements Listener {

	/** @var ScoreHud */
	private $plugin;

	public function __construct(ScoreHud $plugin) {
		$this->plugin = $plugin;
	}

	public function onWorldChange(EntityTeleportEvent $event): void {
		if (!ScoreHudSettings::isMultiWorld()) {
			return;
		}

		$from = $event->getFrom();
		$to = $event->getTo();

		if ($from->getWorld()->getFolderName() === $to->getWorld()->getFolderName()) {
			return;
		}

		$player = $event->getEntity();

		if (!$player instanceof Player or !$player->spawned) {
			return;
		}

		PlayerManager::getNonNull($player)->handle($to->getWorld()->getFolderName());
	}

	public function onServerTagUpdate(ServerTagUpdateEvent $event): void {
		$this->updateServerTag($event->getTag());
	}

	public function onServerTagsUpdate(ServerTagsUpdateEvent $event): void {
		foreach ($event->getTags() as $tag) {
			$this->updateServerTag($tag);
		}
	}

	public function onPlayerTagUpdate(PlayerTagUpdateEvent $event): void {
		$this->updateTag($event->getPlayer(), $event->getTag());
	}

	public function onPlayerTagsUpdate(PlayerTagsUpdateEvent $event): void {
		foreach ($event->getTags() as $tag) {
			$this->updateTag($event->getPlayer(), $tag);
		}
	}

	private function updateServerTag(ScoreTag $tag): void {
		foreach (PlayerManager::getAll() as $session) {
			$this->updateTag($session->getPlayer(), $tag);
		}
	}

	private function updateTag(Player $player, ScoreTag $newTag): void {
		if (
			!$player->isOnline() ||
			ScoreHudSettings::isInDisabledWorld($player->getWorld()->getFolderName()) ||
			is_null($session = PlayerManager::get($player)) ||
			is_null($scoreboard = $session->getScoreboard()) ||
			is_null($scoreTag = $scoreboard->getTag($newTag->getName()))
		) {
			return;
		}

		$scoreTag->setValue($newTag->getValue());

		if (ScoreHudSettings::isSingleLineUpdateMode()) $scoreboard->handleSingleTagUpdate($scoreTag);
		else $scoreboard->update()->display();
	}
}
