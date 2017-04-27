<? namespace Intervolga\Migrato\Tool;

class ColorLog
{
	private $foregroundColors = array();
	private $backgroundColors = array();

	public function __construct()
	{
		$this->foregroundColors['black'] = '0;30';
		$this->foregroundColors['dark_gray'] = '1;30';
		$this->foregroundColors['blue'] = '0;34';
		$this->foregroundColors['light_blue'] = '1;34';
		$this->foregroundColors['green'] = '0;32';
		$this->foregroundColors['light_green'] = '1;32';
		$this->foregroundColors['cyan'] = '0;36';
		$this->foregroundColors['light_cyan'] = '1;36';
		$this->foregroundColors['red'] = '0;31';
		$this->foregroundColors['light_red'] = '1;31';
		$this->foregroundColors['purple'] = '0;35';
		$this->foregroundColors['light_purple'] = '1;35';
		$this->foregroundColors['brown'] = '0;33';
		$this->foregroundColors['yellow'] = '1;33';
		$this->foregroundColors['light_gray'] = '0;37';
		$this->foregroundColors['white'] = '1;37';

		$this->backgroundColors['black'] = '40';
		$this->backgroundColors['red'] = '41';
		$this->backgroundColors['green'] = '42';
		$this->backgroundColors['yellow'] = '43';
		$this->backgroundColors['blue'] = '44';
		$this->backgroundColors['magenta'] = '45';
		$this->backgroundColors['cyan'] = '46';
		$this->backgroundColors['light_gray'] = '47';

		$this->foregroundColors['ok'] = $this->foregroundColors['green'];
		$this->foregroundColors['warning'] = $this->foregroundColors['yellow'];
		$this->foregroundColors['fail'] = $this->foregroundColors['red'];
		$this->foregroundColors['info'] = $this->foregroundColors['light_blue'];

		$this->backgroundColors['ok'] = $this->backgroundColors['green'];
		$this->backgroundColors['fail'] = $this->backgroundColors['red'];
	}

	/**
	 * @param string $string
	 * @param string $foregroundColor
	 * @param string $backgroundColor
	 *
	 * @return string
	 */
	public static function getColoredString($string, $foregroundColor = "", $backgroundColor = "")
	{
		$colored_string = "";
		if (Page::isCli())
		{
			$instance = new self();

			if ($instance->foregroundColors[$foregroundColor])
			{
				$colored_string .= "\033[" . $instance->foregroundColors[$foregroundColor] . "m";
			}
			if ($instance->backgroundColors[$backgroundColor])
			{
				$colored_string .= "\033[" . $instance->backgroundColors[$backgroundColor] . "m";
			}

			$colored_string .= $string . "\033[0m";
		}
		else
		{
			$colored_string = $string;
		}

		return $colored_string;
	}
}

