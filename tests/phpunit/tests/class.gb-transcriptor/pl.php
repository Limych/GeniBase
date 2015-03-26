<?php

/**
 * @group transcriptor
 */
class Tests_Transcriptor_PL extends GB_UnitTestCase {
	function test_transcribes() {
		$data = array(
				'ru' => array(
					'Adam'			=> 'Адам',				'Stępowska'		=> 'Стемповская',
					'Bądkowo'		=> 'Бондково',			'Dąbkowo'		=> 'Домбково',
					'Bogdan'		=> 'Богдан',			'Solec'			=> 'Солец',
					'Cirlić'		=> 'Цирлиць',			'Chamiec'		=> 'Хамец',
					'Życie'			=> 'Жиче',				'Czerniatowicz'	=> 'Чернятович',
					'Derdowski'		=> 'Дердовский',		'Dzianisz'		=> 'Дзяниш',
					'Brodzki'		=> 'Бродский',			'Brodzka'		=> 'Бродская',
					'Niedźwiedź'	=> 'Недзведзь',			'Dżunkowski'	=> 'Джунковский',
					'Eugeniusz'		=> 'Эугениуш',			'Celina'		=> 'Целина',
					'Stępowski'		=> 'Стемповский',		'Wałęsa'		=> 'Валенса',
					'Filip'			=> 'Филип',				'Gotard'		=> 'Готард',
					'Huta'			=> 'Хута',				'Emil'			=> 'Эмиль',
					'Stępowski'		=> 'Стемповский',		'Antoni'		=> 'Антоний',
					'Zosia'			=> 'Зося',				'Adrian'		=> 'Адриан',
					'Marian'		=> 'Мариан',			'Dziąmba'		=> 'Дзёмба',
					'Сiąpała'		=> 'Циомпала',			'Związkowiec'	=> 'Звёнзковец',
					'Ciągło'		=> 'Ционгло',			'Wojciech'		=> 'Войцех',
					'Andrzej'		=> 'Анджей',			'Balcerzak'		=> 'Бальцежак',
					'Celjowski'		=> 'Цельовский',		'Chajecki'		=> 'Хаенцкий',
					'Ciepka'		=> 'Цемпка',			'Cieslak'		=> 'Цесляк',
					'Ciolek'		=> 'Циолек',			'Gjadla'		=> 'Гьондла',
					'Jacek'			=> 'Яцек',				'Jaczkowski'	=> 'Йончковский',
					'Jakobik'		=> 'Якубик',			'Jebrzycki'		=> 'Ембжицкий',
					'Joniec'		=> 'Йонец',				'Juliusz'		=> 'Юлиуш',
					'Kopjewski'		=> 'Копьевский',		'Koscinska'		=> 'Косьциньская',
					'Krystyna'		=> 'Кристина',			'Krzysztof'		=> 'Кшиштоф',
					'Ksawery'		=> 'Ксаверий',			'Kyrljecik'		=> 'Кырльенцик',
					'Lacaz'			=> 'Ляцаз',				'Lodynski'		=> 'Лодыньский',
					'Lorentowicz'	=> 'Лёрентович',		'Lubecki'		=> 'Любецкий',
					'Maciejowice'	=> 'Мацеёвице',			'Mariusz'		=> 'Мариуш',
					'Osjakow'		=> 'Осьякув',			'Piekna'		=> 'Пенкна',
					'Przeglad'		=> 'Пшеглёнд',			'Przyjaciolka'	=> 'Пшиячулка',
					'Pyru'			=> 'Пыру',				'Roman'			=> 'Роман',
					'Rzeczywistosc'	=> 'Жечивистость',		'Rzytka'		=> 'Житка',
					'Stanislaw'		=> 'Станислав',			'Szczawiej'		=> 'Щавей',
					'Szymon'		=> 'Шимон',				'Wladyslaw'		=> 'Владислав',
					'Zajac'			=> 'Зайонц',			'Zbigniew'		=> 'Збигнев',
					'Ziobro'		=> 'Зёбро',				'Zolnierz wolnosci'	=> 'Жолнеж вольности',
					'Zorz'			=> 'Жорж',				'Zrodlowski'	=> 'Зьрудловский',
				)
		);
		foreach ($data as $to_lang => $pairs){
			foreach ($pairs as $src => $res)
				$this->assertEquals($res, GB_Transcriptor::transcript($src, 'pl', $to_lang));
		}
	}

/*	function test_tranliterates() {
		$data = array(
				'ru' => array(
					''	=> '',
				)
		);
		foreach ($data as $to_lang => $pairs){
			foreach ($pairs as $src => $res)
				$this->assertEquals($res, GB_Transcriptor::transcript($src, 'pl', $to_lang, GB_Transcriptor::MODE_TRANSLITERATE));
		}
	}/**/
}
