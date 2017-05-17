<?namespace Intervolga\Migrato\Tool\Console;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\Output as SymfonyOutput;

class Output extends ConsoleOutput
{
	protected $isWindowsCharset = false;

	public function __construct($verbosity = self::VERBOSITY_NORMAL, $decorated = false, OutputFormatterInterface $formatter = null)
	{
		parent::__construct($verbosity, $decorated, $formatter);
		$this->initStyles();
	}

	public function setWindowsCharset($isWindowsCharset = true)
	{
		$this->isWindowsCharset = $isWindowsCharset;
	}

	public function write($messages, $newline = false, $options = SymfonyOutput::OUTPUT_NORMAL)
	{
		if ($this->isWindowsCharset)
		{
			if (is_array($messages))
			{
				foreach ($messages as $i => $message)
				{
					$messages[$i] = iconv('utf-8', 'cp1251', $message);
				}
			}
			else
			{
				$messages = iconv('utf-8', 'cp1251', $messages);
			}
		}
		parent::write($messages, $newline, $options);
	}

	protected function initStyles()
	{
		$style = new OutputFormatterStyle('blue', null, array('bold', 'blink'));
		$this->getFormatter()->setStyle('info', $style);

		$style = new OutputFormatterStyle('green');
		$this->getFormatter()->setStyle('ok', $style);

		$style = new OutputFormatterStyle('red');
		$this->getFormatter()->setStyle('fail', $style);
	}
}