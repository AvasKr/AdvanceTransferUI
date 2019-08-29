<?php

/**
 * @name AdvanceTransferUI
 * @main AvasKr\AdvanceTransferUI
 * @version 1.0.0
 * @author AvasKr
 * @api 3.9.2
 */

namespace AvasKr;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\command\{
    Command, CommandSender
};
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;

class AdvanceTransferUI extends PluginBase{
    protected $config;
    public $db;
    public static $prefix = '§b알림 ||§7 ';

    /** @var int */
    public const SERVER_UI_FORM = 29482;

    public function onEnable()
    {
        if(!file_exists($this->getDataFolder()))
            @mkdir($this->getDataFolder());
        $this->config = new Config($this->getDataFolder() . 'config.yml', Config::YAML);
        $this->db = $this->config->getAll();
        $this->command([
            "서버이동" => "서버이동 명령어 입니다. || made by AvasKr",
            "서버이동관리" => "서버이동관리 명령어 입니다. || made by AvasKr"
        ]);
        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
    }
    public function onDisable()
    {
        if($this->config instanceof Config){
            $this->config->setAll($this->db);
            $this->config->save();
        }
    }
    public function command(array $array)
    {
        foreach($array as $name => $description){
            $command = new \pocketmine\command\PluginCommand($name, $this);
            $command->setDescription($description);
            Server::getInstance()->getCommandMap()->register($name, $command);
        }
    }
    public function onCommand(CommandSender $player, Command $command, string $label, array $args): bool
    {
        if($command->getName() === '서버이동관리'){
            if(!$player->isOp()){
                $player->sendMessage(self::$prefix . '당신은 이 명령어를 사용할 권한이 없습니다.');
                return true;
            }
            if(!isset($args[0]))
                $args[0] = 'x';
            switch($args[0]){
                case '추가':
                    if(!isset($args[1])){
                        $player->sendMessage(self::$prefix . '서버이름을 적어주셔야 합니다.');
                        return true;
                    }
                    if(!isset($args[2])){
                        $player->sendMessage(self::$prefix . '서버아이피를 적어주셔야 합니다.');
                        return true;
                    }
                    if(!isset($args[3]) || !is_numeric($args[3]))
                        $args[3] = 19132;
                    if($this->isServer($args[1])){
                        $player->sendMessage(self::$prefix . '해당 서버는 이미 존재합니다.');
                        return true;
                    }
                    $this->addServer($player, $args[1], $args[2], $args[3]);
                    $player->sendMessage(self::$prefix . '해당 서버를 추가했습니다.');
                    break;
                case '제거':
                    if(!isset($args[1])){
                        $player->sendMessage(self::$prefix . '서버이름을 적어주셔야 합니다.');
                        return true;
                    }
                    if(!$this->isServer($args[1])){
                        $player->sendMessage(self::$prefix . '해당 서버는 존재하지 않습니다.');
                        return true;
                    }
                    $this->deleteServer($player, $args[1]);
                    break;
                default:
                    $player->sendMessage(self::$prefix . '/서버이동관리 추가 (서버명) (아이피) (포트:19132) | 서버이동 서버를 추가합니다.');
                    $player->sendMessage(self::$prefix . '/서버이동관리 제거 (서버명) | 서버이동 서버를 제거합니다.');
                    break;
            }
        }
        if($command->getName() === '서버이동'){
            $json = json_encode((new ServerForms($this))->getForms());
            $packet = new ModalFormRequestPacket();
            $packet->formId = self::SERVER_UI_FORM;
            $packet->formData = $json;
            if($player instanceof Player){
                $player->dataPacket($packet);
            } else {
                $player->sendMessage(self::$prefix . '인게임에서만 사용이 가능합니다.');
            }
            return true;
        }
        return true;
    }
    public function isServer(string $name)
    {
        return isset($this->db [$name]) ? true : false;
    }
    public function addServer(Player $player = null, string $name, string $address, int $port = 19132)
    {
        $this->db [$name] = [];
        $this->db [$name] ['address'] = $address;
        $this->db [$name] ['port'] = $port;
        if($player !== null){
            $this->db [$name] ['creature'] = $player->getName();
        } else {
            $this->db [$name] ['creature'] = "Unkown";
        }
    }
    public function deleteServer(Player $player = null, string $name)
    {
        unset($this->db [$name]);
        $this->getLogger()->notice('' . $name . ' Transfer Server Delete By ' . $player !== null ? '' . $player->getName() : 'Unkown');
    }
}
class ServerForms{
    protected $plugin;

    public function __construct(AdvanceTransferUI $plugin)
    {
        $this->plugin = $plugin;
    }

    public function getForms()
    {
        $arr = [];
        foreach($this->plugin->db as $name => $value){
            $arr[] = [ "text" => "§b> §f{$name} 서버 이동\n§f아이피 - §b{$this->plugin->db[$name]["address"]}" ];
        }
        return [
            'type' => 'form',
            'title' => '§l§b[§f서버 이동§b]§r',
            'content' => "\n§f원하시는 서버를 눌러 이동해보세요!\n\n§f제작자 - 아바스(github.com/AvasKr)",
            'buttons' => $arr
        ];
    }
}
class EventListener implements Listener{
    protected $plugin;

    public function __construct(AdvanceTransferUI $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onReceive(DataPacketReceiveEvent $event): void
    {
        $packet = $event->getPacket();
        $player = $event->getPlayer();
        if($packet instanceof ModalFormResponsePacket){
            $result = json_decode($packet->formData);
            if($packet->formId === AdvanceServerUI::SERVER_UI_FORM){
                if(is_null($result)){
                    return;
                }
                $arr = [];
                $index = 0;
                foreach($this->plugin->db as $name => $value){
                    $arr[$index++] = $name;
                }
                if(isset($arr[$result])){
                    $address = $this->plugin->db [$name] ['address'];
                    $port = $this->plugin->db [$name] ['port'];
                    $player->transfer($address, $port, "§l§b[§f{$name}서버로 이동!§b]");
                }
            }
        }
    }
}
