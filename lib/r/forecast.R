args <- commandArgs(trailingOnly=TRUE)

library("stringr")   
library("jsonlite")
btc <- jsonlite::fromJSON(str_replace_all(args[1], "%26", "&"), simplifyVector = TRUE)
print(btc)
	  
library("xts")

xts_values <- xts(btc[3]$value, as.POSIXct(unlist(btc$datetime), format = "%Y-%m-%d %H:%M:%S"))
frequency <- 0
if (length(args) > 2) frequency <- as.numeric(args[3])
						   
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
