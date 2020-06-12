<?php

/**
 * @name         LevelDay
 * @main         LevelDay\LevelDay
 * @author       OneiricDay
 * @version      Master - Beta 1
 * @api          3.0.0
 * @description (!) 레벨 시스템
 */


namespace LevelDay;


use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;

use pocketmine\Player;
use pocketmine\Server;

use pocketmine\utils\Config;
use pocketmine\form\Form as OriginalForm;

use pocketmine\math\Vector3;
use pocketmine\level\particle\DustParticle;

use pocketmine\event\player\PlayerJoinEvent;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;

use PartyDay\PartyDay;



class LevelDay extends PluginBase implements Listener
{


	/** @var string */
	public static $prefix = '§e§l LevelDay > §r§f';


	const MAX_LEVEL = 100;
	const NEED_EXP = 500;
	
	const PARTY_EXP_PERCENT = 30;



    /**
     * @param string|Player $player
     * @return string
     */

	public static function convertName ($player) : string
	{
		
		return $player instanceof Player ? strtolower ($player->getName()) : strtolower ($player);
		
	}


    /**
     * @param string|Player $player
     * @return bool
     */

	public static function isExistData ($player) : bool
	{

		return isset (self::$playerData [self::convertName ($player)]);

	}
	
	
    /**
     * @param string|Player $player
     * @return bool
     */

	public static function createData ($player) : bool
	{

		if (self::isExistData ($player))
			return false;
		
		self::$playerData [self::convertName ($player)] = ['레벨' => 1, '경험치' => 0];
		return true;

	}


    /**
     * @param string|Player $player
     * @return int
     */

	public static function getLevel ($player) : int
	{

		if (! self::isExistData ($player))
			return -1;

		return self::$playerData [self::convertName ($player)]['레벨'];

	}
	
	
    /**
     * @param string|Player $player
     * @return int
     */

	public static function getExp ($player) : int
	{

		if (! self::isExistData ($player))
			return -1;

		return self::$playerData [self::convertName ($player)]['경험치'];

	}


    /**
     * @param string|Player $player
	 * @param int $level
     */

	public static function setLevel ($player, int $level)
	{

		if (! self::isExistData ($player))
			return;

		if ($level > self::MAX_LEVEL)
		{

			$player->sendMessage (self::$prefix . self::MAX_LEVEL . ' 레벨을 넘을 수 없습니다!');
			$level = self::MAX_LEVEL;

		}

		self::$playerData [self::convertName ($player)]['레벨'] = $level;

	}
	
	
    /**
     * @param string|Player $player
	 * @param int $level
     */
	 
	public static function addLevel ($player, int $level = 1)
	{
		
		if (! self::isExistData ($player))
			return;

		$beforeLevel = self::getLevel ($player);
		$afterLevel = $beforeLevel + $level;

		self::setLevel ($player, $afterLevel);

		if ($afterLevel > self::MAX_LEVEL)
			return;
		
		if (($p = Server::getInstance()->getPlayerExact (self::convertName ($player))) === null)
			return;

		$p->addTitle ('§f레벨 §e업!', '- 레벨 업을 축하합니다 -');
		$size = 10;

		for ($x = -$size; $x <= $size; $x++)
		for ($y = -$size; $y <= $size; $y++)
		for ($z = -$size; $z <= $size; $z++)

		if (($vec = (new Vector3($x * 0.3, $y * 0.3, $z *0.3))->add($p))->distance($p) <= $size/10)
			$p->level->addParticle (new DustParticle ($vec->add (0, 2, 0), mt_rand (150, 250), mt_rand (150, 250), mt_rand (150, 250)));

	}


    /**
     * @param string|Player $player
	 * @param int $exp
     */

	public static function setExp ($player, int $exp)
	{

		if (! self::isExistData ($player))
			return;

		self::$playerData [self::convertName ($player)]['경험치'] = $exp;

	}

	
    /**
     * @param string|Player $player
	 * @param int $exp
	 * @param bool $partyExp
     */ 

	public static function addExp ($player, int $exp, bool $partyExp = true)
	{

		if (! self::isExistData ($player))
			return;

		if (($afterExp = self::getExp ($player) + $exp) > self::getNeedExp ($player))
		{

			self::addLevel ($player);
			self::setExp ($player, 0);

			return;

		}

		self::setExp ($player, $afterExp);
		
		if (($p = Server::getInstance()->getPlayerExact (self::convertName ($player))) !== null)
			$p->sendPopup ("§e§l■ §r§f{$exp} 경험치 획득! §e(" . self::getExp ($player) . '/' . self::getNeedExp ($player) . ')');

		if (! $partyExp)
			return;
		
		if (! PartyDay::hasParty ($player))
			return;
		
		$name = self::convertName ($player);
		$givenExp = floor ($exp * self::PARTY_EXP_PERCENT / 100);

		foreach (PartyDay::getPartyOnlineMembers (PartyDay::getParty ($player)) as $k => $v)
		{

			if (strtolower ($v->getName()) === strtolower ($player->getName()))
				continue;

			self::addExp ($v, $givenExp, false);
			$v->sendPopup ("§f§l===§e[ §f파티 경험치 §e]§f===§r\n§e{$name}§f님의 경험치 획득으로 나도 §e{$givenExp} 경험치§f를 받았습니다 (30%%)");

		}

	}


    /**
     * @param string|Player $player
	 * @return bool
     */

	public static function getNeedExp ($player)
	{

		return self::getLevel ($player) * self::NEED_EXP;

	}



	/** @var array */
	public static $playerData = [];


	/** @var Config */
	public static $config = null;


	public function onEnable () : void
	{

		self::$config = new Config ($this->getDataFolder() . 'playerData.yml', Config::YAML, []);
		self::$playerData = self::$config->getAll();

		$this->getLogger()->info (count (self::$playerData) . '개의 데이터가 로딩되었습니다.');
		$this->getServer()->getPluginManager()->registerEvents ($this, $this);

        $cmd = new PluginCommand ('레벨순위', $this);
        $cmd->setDescription ('레벨 순위를 확인해보세요');

        Server::getInstance()->getCommandMap()->register ('레벨순위', $cmd);
		
        $cmd = new PluginCommand ('레벨정보', $this);
        $cmd->setDescription ('레벨 정보를 확인해보세요');

        Server::getInstance()->getCommandMap()->register ('레벨정보', $cmd);

	}


	public function onDisable () : void
	{

		self::$config->setAll (self::$playerData);
		self::$config->save ();

		$this->getLogger()->info (count (self::$playerData) . "개의 데이터가 저장되었습니다.");

	}
	
	
	public function onJoin (PlayerJoinEvent $event)
	{

		$player = $event->getPlayer();

		if (! self::isExistData ($player))
			self::createData ($player);

	}


	public function onCommand (CommandSender $player, Command $command, string $label, array $args) : bool
	{

		if ($command->getName() === '레벨순위')
		{

			$form = new Form ();
			$form->setTitle ('§l* 레벨 시스템 | OneiricDay');
			$form->setContent ("\n      레벨 순위를 확인해보세요!\n      §7- 제작자: OneiricDay -\n\n");

			$data = [];

			foreach (self::$playerData as $k => $v)
				$data [$k] = ($v ['레벨'] - 1) * self::NEED_EXP + $v ['경험치'];

			arsort ($data);

			foreach ($data as $k => $v)
				$form->addButton ($k . "\n" . self::getLevel ($k) . ' 레벨, ' . self::getExp ($k) . ' 경험치');

			$form->sendForm ($player);

		}

		if ($command->getName() === '레벨정보')
		{
			
			$target = $args[0] ?? 'x';
			
			if ($target === 'x')
			{

				$player->sendMessage (self::$prefix . '레벨을 조회할 유저를 입력해주세요.');
				return true;

			}

			if (! self::isExistData ($target))
			{

				$player->sendMessage (self::$prefix . $target . '님의 데이터가 존재하지 않습니다.');
				return true;

			}

			$player->sendmessage (self::$prefix . $target . '님의 레벨: ' . self::getLevel ($target) . ' 레벨, 경험치: ' . self::getExp ($target) . ' 경험치');

		}

		return true;

	}


}

class Form implements OriginalForm
{


	protected $data = [

		'type' => 'form',
		'title' => '',
		'content' => '',
		'buttons' => []

	];

	protected $call = null;


	public function setTitle (string $title)
	{

		$this->data ['title'] = $title;

	}

	public function setContent (string $content)
	{

		$this->data ['content'] = $content;

	}

	public function addButton (string $button)
	{

		$this->data ['buttons'][] = ['text' => $button];

	}

	public function handleResponse (Player $player, $data) : void
	{

	}

	public function jsonSerialize () : array
	{

		return $this->data;

	}

	public function sendForm (Player $player)
	{

		$player->sendForm ($this);

	}

}
?>