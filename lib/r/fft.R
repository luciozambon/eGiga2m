args <- commandArgs(trailingOnly=TRUE)

library("stringr")   
library("jsonlite")
# print(str_replace_all(args[1], "%26", "&"))
btc <- jsonlite::fromJSON(str_replace_all(args[1], "%26", "&"), simplifyVector = TRUE)
print(btc)
# plot(btc$value)

freqdomaindata <- fft(append((btc$value),numeric(nextn(length(btc$value), factors = c(2, 3, 5))-length(btc$value))))
format <- 'Mod,Phase';
# print(freqdomaindata)
if (length(args)>1) format <- args[2]
if (format=='Re') {
	print('Re')
	print(Re(freqdomaindata))
} else if (format=='Im') {
	print('Im')
	print(Im(freqdomaindata))
} else if (format=='Re,Im') {
	print('Re')
	print(Re(freqdomaindata))
	print('Im')
	print(Im(freqdomaindata))
} else if (format=='Mod') {
	print('Mod')
	print(Mod(freqdomaindata))
} else if (format=='Phase') {
	print('Phase')
	print(Arg(freqdomaindata))
} else {
	print('Mod')
	print(Mod(freqdomaindata))
	print('Phase')
	print(Arg(freqdomaindata))
}
