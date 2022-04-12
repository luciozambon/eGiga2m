maxdata <- 100000
minfreq <- 3
minthreshold <- 20

args <- commandArgs(trailingOnly=TRUE)

library("stringr")   
library("jsonlite")
btc <- jsonlite::fromJSON(str_replace_all(args[1], "%26", "&"), simplifyVector = TRUE)

params <- str_split(args[1], "&period=")
period <- as.numeric(str_split(params[[1]][2], "&")[[1]][1])

		   
if (length(btc$datetime) > maxdata) {
	print("too many samples in input time series, max:")
	print(maxdata)
	stop()
}
print(btc)
	  
library("xts")

xts_values <- xts(btc[3]$value, as.POSIXct(unlist(btc$datetime), format = "%Y-%m-%d %H:%M:%S"))
frequency <- 0
if (length(args) > 2) {
	frequency <- as.numeric(args[3])
}
else {
	m <- Mod(fft(append((btc$value),numeric(nextn(length(btc$value), factors = c(2, 3, 5))-length(btc$value)))))
	maxm <- max(m)/mean(m)
	indexm <- which.max(m)
	if (indexm > minfreq && maxm > minthreshold) frequency <- indexm * period
}
if (frequency > 0) attr(xts_values, 'frequency') <- frequency
# print(xts_values)

library("forecast")
# fit <- auto.arima(xts_values[,1], D = 1, seasonal = FALSE)
fit <- auto.arima(xts_values[,1], D = 1, seasonal = (frequency > 0))
# summary(fit)

h <- 168
if (length(args) > 1) h <- as.numeric(args[2])

fcast <- forecast(fit, h)
print(fcast)
