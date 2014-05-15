CoinWatch
=========

Simple PHP to track your bought coins on various exchanges

Usage:
fill data.ini with the coins you've bought or mined like this
name = amount,paid
inside the [exchange] you want to watch, supported right now:
mintpal
bittrex
polo


So if you've bought 1000 WC on Poloniex at 600 you look for
[polo]

and add
WC = 1000, 0.00000600

that's all :)
