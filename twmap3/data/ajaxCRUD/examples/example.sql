CREATE TABLE tblDemo(
pkID INT PRIMARY KEY AUTO_INCREMENT,
fldField1 VARCHAR(45),
fldField2 VARCHAR(45),
fldCertainFields VARCHAR(40),
fldLongField TEXT,
fldCheckbox TINYINT
);

CREATE TABLE tblDemo2(
pkID INT PRIMARY KEY AUTO_INCREMENT,
fldField1 VARCHAR(45),
fldField2 VARCHAR(45),
fldCertainFields VARCHAR(40),
fldLongField TEXT
);

CREATE TABLE IF NOT EXISTS `tblfriend` (
  `pkFriendID` int(11) NOT NULL AUTO_INCREMENT,
  `fldName` varchar(25) DEFAULT NULL,
  `fldAddress` varchar(30) DEFAULT NULL,
  `fldCity` varchar(20) DEFAULT NULL,
  `fldState` char(2) DEFAULT NULL,
  `fldZip` varchar(5) DEFAULT NULL,
  `fldPhone` varchar(15) DEFAULT NULL,
  `fldEmail` varchar(35) DEFAULT NULL,
  `fldBestFriend` char(1) DEFAULT NULL,
  `fldDateMet` date DEFAULT NULL,
  `fldFriendRating` char(1) DEFAULT NULL,
  `fldOwes` double(6,2) DEFAULT NULL,
  `fldPicture` varchar(30) DEFAULT NULL,
  `fkMarriedTo` tinyint(4) DEFAULT NULL,
  PRIMARY KEY (`pkFriendID`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `tblfriend`
--

INSERT INTO `tblFriend` (`pkFriendID`, `fldName`, `fldAddress`, `fldCity`, `fldState`, `fldZip`, `fldPhone`, `fldEmail`, `fldBestFriend`, `fldDateMet`, `fldFriendRating`, `fldOwes`, `fldPicture`, `fkMarriedTo`) VALUES
(1, 'Sean Dempsey', '13 Back River Road', 'Dover', 'NH', '03820', '(603) 978-8841', 'sean@loudcanvas.com', 'N', '2011-10-27', '5', 122.01, '', 1),
(2, 'Justin Rigby', '22 Farmington Rd', 'Rochester', 'VT', '05401', '(802) 661-4051', 'sean@seandempsey.com', '', '2011-10-19', '1', 22.00, '', 2),
(3, 'Ryan Dempsey', '', '', 'VT', '', '', 'ryan@dempsey.com', '', '2011-10-20', '', 0.00, '', 3),
(4, 'Justin Beiber', '22 Mason Dr', 'Somersworth', 'IA', '32232', '(332) 223-3223', 'sean@seandempsey.com', 'Y', '0000-00-00', '4', 0.00, '', 2),
(5, 'Tim Boyle', 'Jason St', 'New Boston', 'CO', '22112', '(112) 111-1111', 'sean@seandempsey.com', 'N', '2014-01-01', '2', 121.00, '', 3);


CREATE TABLE IF NOT EXISTS `tblLadies` (
  `pkLadyID` int(11) NOT NULL AUTO_INCREMENT,
  `fldName` varchar(25) DEFAULT NULL,
  `fldSort` int(11) DEFAULT NULL,
  PRIMARY KEY (`pkLadyID`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

--
-- Dumping data for table `tblLadies`
--

INSERT INTO `tblLadies` (`pkLadyID`, `fldName`, `fldSort`) VALUES
(1, 'Emily Benson', 1),
(2, 'Sharon Nelson', 2),
(3, 'Kirsten Leavitt', 3);