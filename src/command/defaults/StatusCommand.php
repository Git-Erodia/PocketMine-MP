<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\CommandSender;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\utils\Process;
use pocketmine\utils\TextFormat;
use function count;
use function floor;
use function microtime;
use function number_format;
use function round;

class StatusCommand extends VanillaCommand{

	public function __construct(string $name){
		parent::__construct(
			$name,
			KnownTranslationFactory::pocketmine_command_status_description()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_STATUS);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(!$this->testPermission($sender)){
			return true;
		}

		$mUsage = Process::getAdvancedMemoryUsage();

		$server = $sender->getServer();
		$sender->sendMessage("§e[§6É§e] §r§aStatut du serveur:");

		$time = (int) (microtime(true) - $server->getStartTime());

		$seconds = $time % 60;
		$minutes = null;
		$hours = null;
		$days = null;

		if($time >= 60){
			$minutes = floor(($time % 3600) / 60);
			if($time >= 3600){
				$hours = floor(($time % (3600 * 24)) / 3600);
				if($time >= 3600 * 24){
					$days = floor($time / (3600 * 24));
				}
			}
		}

		$uptime = ($minutes !== null ?
				($hours !== null ?
					($days !== null ?
						"$days jour" . ($days > 1 ? "s" : "") . " "
					: "") . "$hours heure" . ($hours > 1 ? "s" : "") . " "
					: "") . "$minutes minute" . ($minutes > 1 ? "s" : "") . " "
			: "") . "$seconds seconde" . ($seconds > 1 ? "s" : "") . " ";

		$sender->sendMessage("§6» §fTemps actif: " . TextFormat::YELLOW . $uptime);

		$tpsColor = TextFormat::GREEN;
		if($server->getTicksPerSecond() < 12){
			$tpsColor = TextFormat::RED;
		}elseif($server->getTicksPerSecond() < 17){
			$tpsColor = TextFormat::GOLD;
		}

		$sender->sendMessage("§6» §fTPS Actuel: {$tpsColor}{$server->getTicksPerSecond()} ({$server->getTickUsage()}%)");
		$sender->sendMessage("§6» §fMoyenne des TPS: {$tpsColor}{$server->getTicksPerSecondAverage()} ({$server->getTickUsageAverage()}%)");

		$bandwidth = $server->getNetwork()->getBandwidthTracker();
		$sender->sendMessage("§6» §fUpload: " . TextFormat::YELLOW . round($bandwidth->getSend()->getAverageBytes() / 1024, 2) . " kB/s");
		$sender->sendMessage("§6» §fDownload: " . TextFormat::YELLOW . round($bandwidth->getReceive()->getAverageBytes() / 1024, 2) . " kB/s");

		$sender->sendMessage("§6» §fThreads: " . TextFormat::YELLOW . Process::getThreadCount());

		$sender->sendMessage("§6» §fRAM du thread principal: " . TextFormat::YELLOW . number_format(round(($mUsage[0] / 1024) / 1024, 2), 2) . " MB.");
		$sender->sendMessage("§6» §fRAM totale: " . TextFormat::YELLOW . number_format(round(($mUsage[1] / 1024) / 1024, 2), 2) . " MB.");
		$sender->sendMessage("§6» §fvRAM totale: " . TextFormat::YELLOW . number_format(round(($mUsage[2] / 1024) / 1024, 2), 2) . " MB.");

		$globalLimit = $server->getMemoryManager()->getGlobalMemoryLimit();
		if($globalLimit > 0){
			$sender->sendMessage(TextFormat::GOLD . "Maximum memory (manager): " . TextFormat::RED . number_format(round(($globalLimit / 1024) / 1024, 2), 2) . " MB.");
		}

		foreach($server->getWorldManager()->getWorlds() as $world){
			$worldName = $world->getFolderName() !== $world->getDisplayName() ? " (" . $world->getDisplayName() . ")" : "";
			$timeColor = $world->getTickRateTime() > 40 ? TextFormat::RED : TextFormat::YELLOW;
			$sender->sendMessage(TextFormat::GOLD . "Monde \"{$world->getFolderName()}\"$worldName: " .
				TextFormat::RED . number_format(count($world->getLoadedChunks())) . TextFormat::GREEN . " chunk" . (count($world->getLoadedChunks()) > 1 ? "s" : "") . ", " .
				TextFormat::RED . number_format(count($world->getEntities())) . TextFormat::GREEN . " entit" . (count($world->getEntities()) > 1 ? "ies" : "y") . ". " .
				"Temps $timeColor" . round($world->getTickRateTime(), 2) . "ms"
			);
		}

		return true;
	}
}
